<?php

/**
 * SAML2 Provider for Hybridauth
 * Copyright (c) e.World Technology Limited. All rights reserved.
 */

namespace PHPMaker2024\FIMS;

use Hybridauth\Exception\Exception;
use Hybridauth\User;
use Hybridauth\HttpClient;
use Hybridauth\Adapter\AbstractAdapter;
use Hybridauth\Adapter\AdapterInterface;
use LightSaml\Binding\AbstractBinding;
use LightSaml\Binding\BindingFactory;
use LightSaml\Context\Profile\MessageContext;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Metadata\IdpSsoDescriptor;
use LightSaml\Model\Metadata\SingleSignOnService;
use LightSaml\Model\Metadata\SingleLogoutService;
use LightSaml\Model\Protocol\AuthnRequest;
use LightSaml\Model\Protocol\Response;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\SamlMessage;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Protocol\StatusCode;
use LightSaml\Model\XmlDSig\SignatureWriter;
use LightSaml\SamlConstants;
use LightSaml\Helper;
use LightSaml\State\Sso\SsoSessionState;
use LightSaml\Resolver\Session\SessionProcessor;
use LightSaml\Error\LightSamlAuthenticationException;
use LightSaml\Credential\X509Certificate;
use LightSaml\Credential\KeyHelper;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;

class Saml2 extends AbstractAdapter implements AdapterInterface
{
    /**
     * Attribute map
     */
    public static array $attributeMap = [
        'givenname' => 'firstName',
        'surname' => 'lastName',
        'emailaddress' => 'email',
    ];

    /**
     * Entity ID
     */
    public string $entityId = '';

    /**
     * X.509 certificate
     */
    public string $certificate = '';

    /**
     * Private key
     */
    protected string $privateKey = '';

    /**
     * Authorization Endpoint
     */
    protected string $authorizeUrl = '';

    /**
     * Redirection Endpoint or Callback
     */
    protected $callback = '';

    /**
     * Response or Logout response
     */
    protected $response = null;

    /**
     * IdP Entity Descriptor
     */
    protected ?EntityDescriptor $idpEntityDescriptor = null;

    /**
     * Binding for Single logout service
     */
    public static $singleSignOnBinding = null;

    /**
     * Single logout service enabled
     */
    public static bool $singleLogoutServiceEnabled = true;

    /**
     * Configure
     */
    protected function configure()
    {
        $this->setCallback($this->config->get('callback'));
        $this->entityId = $this->config->get('entityId') ?: FullUrl(GetApiUrl(Config('API_METADATA_ACTION')));
        $this->certificate = $this->config->get('certificate');
        $this->privateKey = $this->config->get('privateKey');
        $this->idpEntityDescriptor = EntityDescriptor::load($this->config->get('idpMetadata'));
        self::$singleSignOnBinding ??= ContainsText($this->config->get('idpMetadata'), 'login.microsoftonline.com')
            ? SamlConstants::BINDING_SAML2_HTTP_POST // Use POST for Azure
            : SamlConstants::BINDING_SAML2_HTTP_REDIRECT;
    }

    /**
     * Initialize
     */
    protected function initialize()
    {
    }

    /**
     * Authenticate
     */
    public function authenticate()
    {
        $this->logger->info(sprintf('%s::authenticate()', $this::class));

        // if ($this->isConnected()) { // Note: Always check the SAML response
        //     return true;
        // }
        try {
            $this->authenticateCheckError(); // Check and set the SAML response
            if (!IsSamlResponse()) {
                $this->authenticateBegin();
            } else {
                $this->authenticateFinish();
            }
        } catch (Exception $e) { // Hybridauth\Exception\Exception
            $this->clearStoredData();
            throw $e;
        }
        return null;
    }

    /**
     * Is connected
     */
    public function isConnected()
    {
        $session = SsoStateStore()->getSpSession($this->idpEntityDescriptor->getEntityID());
        return $session !== null;
    }

    /**
     * Authorization Request Error Response
     */
    protected function authenticateCheckError()
    {
        $this->response = null; // Reset
        if (IsSamlResponse()) {
            $request = Request::createFromGlobals();
            $bindingFactory = new BindingFactory();
            $binding = $bindingFactory->getBindingByRequest($request);
            $messageContext = new MessageContext();
            $binding->receive($request, $messageContext);
            $response = $messageContext->getMessage(); // \LightSaml\Model\Protocol\Response
            $this->response = $response; // Save response
            if ($response->getStatus() && $response->getStatus()->isSuccess()) {
                return;
            }
            $this->checkStatusResponse($response);
        }
    }

    /**
     * Check StatusResponse
     *
     * @param StatusResponse $response
     *
     * @return void
     */
    protected function checkStatusResponse($response)
    {
        if (!$response) {
            return;
        }
        $status = $response->getStatus();
        if ($status === null) {
            $message = 'Status response does not have Status set';
            $this->logger->error($message);
            if ($response instanceof Response) {
                throw new LightSamlAuthenticationException($response, $message);
            } else {
                throw new LightSamlException($message);
            }
        }
        $message = $status->getStatusCode()->getValue() . "\n" . $status->getStatusMessage();
        if ($status->getStatusCode()->getStatusCode()) {
            $message .= "\n" . $status->getStatusCode()->getStatusCode()->getValue();
        }
        if (trim($message) !== '') {
            $message = 'Unsuccessful SAML response: ' . $message;
            $this->logger->error($message);
            if ($response instanceof Response) {
                throw new LightSamlAuthenticationException($response, $message);
            } else {
                throw new LightSamlException($message);
            }
        }
    }

    /**
     * Initiate the authorization protocol
     *
     * Build Authorization URL for Authorization Request and redirect the user-agent to the Authorization Server.
     */
    protected function authenticateBegin()
    {
        $authUrl = $this->getAuthorizeUrl();
        if (self::$singleSignOnBinding == SamlConstants::BINDING_SAML2_HTTP_REDIRECT) { // Redirect
            if ($this->config->get('debug_mode')) {
                $this->logger->debug(sprintf('%s::authenticateBegin(), redirecting user to:', $this::class), [$authUrl]);
            }
            HttpClient\Util::redirect($authUrl);
        } else { // Post ($authUrl is HTML)
            echo $authUrl;
            exit();
        }
    }

    /**
     * Finalize the authorization process
     */
    protected function authenticateFinish()
    {
        if ($this->config->get('debug_mode')) {
            $this->logger->debug(
                sprintf('%s::authenticateFinish(), callback url:', $this::class),
                [HttpClient\Util::getCurrentUrl(true)]
            );
        }

        // Get assertions
        $assertions = $this->response->getAllAssertions();

        // Process assertions and set SSO state
        $sessionProcessor = SessionProcessor();
        $sessionProcessor->processAssertions(
            $assertions,
            $this->entityId,
            $this->idpEntityDescriptor->getEntityID()
        );
    }

    /**
     * Build Authorization URL for Authorization Request
     *
     * @param array $parameters
     *
     * @return string Authorization URL
     */
    protected function getAuthorizeUrl($parameters = [])
    {
        $idpSsoDescriptor = $this->idpEntityDescriptor->getFirstIdpSsoDescriptor();
        $sso = $idpSsoDescriptor->getFirstSingleSignOnService(self::$singleSignOnBinding);
        $wantAuthnRequestsSigned = $idpSsoDescriptor->getWantAuthnRequestsSigned();
        $authnRequest = new AuthnRequest();
        $authnRequest->setAssertionConsumerServiceURL($this->callback)
            ->setProtocolBinding(self::$singleSignOnBinding)
            ->setID(Helper::generateID())
            ->setIssueInstant(new \DateTime())
            ->setDestination($sso->getLocation())
            ->setIssuer(new Issuer($this->entityId));
        $bindingFactory = new BindingFactory();
        $binding = $bindingFactory->create(self::$singleSignOnBinding);
        $messageContext = new MessageContext();
        $messageContext->setMessage($authnRequest);
        if ($wantAuthnRequestsSigned && $this->certificate && $this->privateKey) {
            $certificate = X509Certificate::fromFile(ServerMapPath($this->certificate, true));
            $privateKey = KeyHelper::createPrivateKey(ServerMapPath($this->privateKey, true), '', true); // Private key is file
            $signature = new SignatureWriter($certificate, $privateKey);
            $authnRequest->setSignature($signature);
            if ($this->config->get('debug_mode')) {
                $this->logger->debug(sprintf('Message signed with fingerprint "%s"', $signature->getCertificate()->getFingerprint()));
            }
        } else {
            if ($this->config->get('debug_mode')) {
                $this->logger->debug('Signing disabled');
            }
        }
        $httpResponse = $binding->send($messageContext);
        if (self::$singleSignOnBinding == SamlConstants::BINDING_SAML2_HTTP_REDIRECT) { // Redirect
            return $httpResponse->getTargetUrl(); // $httpResponse is \Symfony\Component\HttpFoundation\RedirectResponse
        } else { // Post
            return $httpResponse->getContent(); // $httpResponse is \LightSaml\Binding\SamlPostResponse (extends \Symfony\Component\HttpFoundation\Response)
        }
    }

    /**
     * Disconnect (Logout)
     */
    public function disconnect()
    {
        if (!self::$singleLogoutServiceEnabled) {
            return;
        }
        if (!IsSamlResponse()) { // Send logout request to IdP
            $idpSsoDescriptor = $this->idpEntityDescriptor->getFirstIdpSsoDescriptor();
            if (!$idpSsoDescriptor) {
                return;
            }
            $slo = $idpSsoDescriptor->getFirstSingleLogoutService();
            if (!$slo) {
                return;
            }
            $session = SsoStateStore()->getSpSession($this->idpEntityDescriptor->getEntityID());
            if (!$session) {
                return;
            }
            $logoutRequest = new LogoutRequest();
            $logoutRequest
                ->setSessionIndex($session->getSessionIndex())
                ->setNameID(new NameID($session->getNameId(), $session->getNameIdFormat()))
                ->setDestination($slo->getLocation())
                ->setID(Helper::generateID())
                ->setIssueInstant(new \DateTime())
                ->setIssuer(new Issuer($this->entityId));
            $bindingFactory = new BindingFactory();
            $redirectBinding = $bindingFactory->create(SamlConstants::BINDING_SAML2_HTTP_REDIRECT); // Assume HTTP-Redirect
            $messageContext = new MessageContext();
            $messageContext->setMessage($logoutRequest);
            $response = $redirectBinding->send($messageContext); // \Symfony\Component\HttpFoundation\RedirectResponse
            HttpClient\Util::redirect($response->getTargetUrl());
        } else { // Logout response from IdP
            $request = Request::createFromGlobals();
            $bindingFactory = new BindingFactory();
            $binding = $bindingFactory->getBindingByRequest($request);
            $messageContext = new MessageContext();
            $binding->receive($request, $messageContext); // \LightSaml\Model\Protocol\LogoutResponse
            $response = $messageContext->getMessage();
            $this->response = $response; // Save response
            if ($response->getStatus() && $response->getStatus()->isSuccess()) {
                SsoStateStore()->terminateSession($this->idpEntityDescriptor->getEntityID()); // Terminate session
                parent::disconnect();
                return;
            }
            $this->checkStatusResponse($logoutResponse);
        }
    }

    /**
     * Get user profile
     *
     * @return array
     */
    public function getUserProfile()
    {
        $userProfile = new User\Profile();
        if ($this->response) {
            $assertions = $this->response->getAllAssertions();
            $attributes = [];
            foreach ($assertions as $assertion) {
                foreach ($assertion->getAllAttributeStatements() as $attributeStatement) {
                    foreach ($attributeStatement->getAllAttributes() as $attribute) {
                        $name = $attribute->getName();
                        if (StartsString('http://', $name)) {
                            $parts = explode('/', $name);
                            $name = array_pop($parts);
                        }
                        $attributes[$name] = $attribute->getFirstAttributeValue();
                    }
                }
            }
            $reflect = new ReflectionClass($userProfile);
            $props = Collection::make(array_column($reflect->getProperties(ReflectionProperty::IS_PUBLIC), 'name'));
            foreach ($attributes as $key => $value) {
                $key = self::$attributeMap[$key] ?? $key;
                $prop = $props->first(fn ($v, $k) => SameText($v, $key));
                if ($prop) {
                    $userProfile->$prop = $value;
                } else {
                    $userProfile->data[$key] = $value;
                }
            }
        }
        return $userProfile;
    }
}
