<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\App;
use Closure;

// Filter for 'Last Month' (example)
function GetLastMonthFilter($FldExpression, $dbid = "")
{
    $today = getdate();
    $lastmonth = mktime(0, 0, 0, $today['mon'] - 1, 1, $today['year']);
    $val = date("Y|m", $lastmonth);
    $wrk = $FldExpression . " BETWEEN " .
        QuotedValue(DateValue("month", $val, 1, $dbid), DataType::DATE, $dbid) .
        " AND " .
        QuotedValue(DateValue("month", $val, 2, $dbid), DataType::DATE, $dbid);
    return $wrk;
}

// Filter for 'Starts With A' (example)
function GetStartsWithAFilter($FldExpression, $dbid = "")
{
    return $FldExpression . Like("'A%'", $dbid);
}

// Global user functions

// Database Connecting event
function Database_Connecting(&$info)
{
    // Example:
    //var_dump($info);
    //if ($info["id"] == "DB" && IsLocal()) { // Testing on local PC
    //    $info["host"] = "locahost";
    //    $info["user"] = "root";
    //    $info["password"] = "";
    //}
}

// Database Connected event
function Database_Connected($conn)
{
    // Example:
    //if ($conn->info["id"] == "DB") {
    //    $conn->executeQuery("Your SQL");
    //}
}

// Language Load event
function Language_Load()
{
    // Example:
    //$this->setPhrase("MyID", "MyValue"); // Refer to language file for the actual phrase id
    //$this->setPhraseClass("MyID", "fa-solid fa-xxx ew-icon"); // Refer to https://fontawesome.com/icons?d=gallery&m=free [^] for icon name
}

function MenuItem_Adding($item)
{
    //var_dump($item);
    //$item->Allowed = false; // Set to false if menu item not allowed
}

function Menu_Rendering()
{
    // Change menu items here
}

function Menu_Rendered()
{
    // Clean up here
}

// Page Loading event
function Page_Loading()
{
    //Log("Page Loading");
}

// Page Rendering event
function Page_Rendering()
{
    //Log("Page Rendering");
}

// Page Unloaded event
function Page_Unloaded()
{
    //Log("Page Unloaded");
}

// AuditTrail Inserting event
function AuditTrail_Inserting(&$rsnew)
{
    //var_dump($rsnew);
    return true;
}

// Personal Data Downloading event
function PersonalData_Downloading($row)
{
    //Log("PersonalData Downloading");
}

// Personal Data Deleted event
function PersonalData_Deleted($row)
{
    //Log("PersonalData Deleted");
}

// One Time Password Sending event
function Otp_Sending($usr, $client)
{
    // Example:
    // var_dump($usr, $client); // View user and client (Email or SMS object)
    // if (SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "email")) { // Possible values, email or SMS
    //     $client->Content = ...; // Change content
    //     $client->Recipient = ...; // Change recipient
    //     // return false; // Return false to cancel
    // }
    return true;
}

// Route Action event
function Route_Action($app)
{
    // Example:
    // $app->get('/myaction', function ($request, $response, $args) {
    //    return $response->withJson(["name" => "myaction"]); // Note: Always return Psr\Http\Message\ResponseInterface object
    // });
    // $app->get('/myaction2', function ($request, $response, $args) {
    //    return $response->withJson(["name" => "myaction2"]); // Note: Always return Psr\Http\Message\ResponseInterface object
    // });
}

// API Action event
function Api_Action($app)
{
    include(dirname(__DIR__, 1) . "/app/api.php");
}

// Container Build event
function Container_Build($builder)
{
    // Example:
    // $builder->addDefinitions([
    //    "myservice" => function (ContainerInterface $c) {
    //        // your code to provide the service, e.g.
    //        return new MyService();
    //    },
    //    "myservice2" => function (ContainerInterface $c) {
    //        // your code to provide the service, e.g.
    //        return new MyService2();
    //    }
    // ]);
}
include(dirname(__DIR__, 1) . "/app/global.php");

// Add listeners
AddListener(DatabaseConnectingEvent::NAME, fn(DatabaseConnectingEvent $event) => Database_Connecting($event));
AddListener(DatabaseConnectedEvent::NAME, fn(DatabaseConnectedEvent $event) => Database_Connected($event->getConnection()));
AddListener(LanguageLoadEvent::NAME, fn(LanguageLoadEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Language_Load")->bindTo($event->getLanguage())());
AddListener(MenuItemAddingEvent::NAME, fn(MenuItemAddingEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "MenuItem_Adding")->bindTo($event->getMenu())($event->getMenuItem()));
AddListener(MenuRenderingEvent::NAME, fn(MenuRenderingEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Menu_Rendering")->bindTo($event->getMenu())($event->getMenu()));
AddListener(MenuRenderedEvent::NAME, fn(MenuRenderedEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Menu_Rendered")->bindTo($event->getMenu())($event->getMenu()));
AddListener(PageLoadingEvent::NAME, fn(PageLoadingEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Page_Loading")->bindTo($event->getPage())());
AddListener(PageRenderingEvent::NAME, fn(PageRenderingEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Page_Rendering")->bindTo($event->getPage())());
AddListener(PageUnloadedEvent::NAME, fn(PageUnloadedEvent $event) => Closure::fromCallable(PROJECT_NAMESPACE . "Page_Unloaded")->bindTo($event->getPage())());
AddListener(RouteActionEvent::NAME, fn(RouteActionEvent $event) => Route_Action($event->getApp()));
AddListener(ApiActionEvent::NAME, fn(ApiActionEvent $event) => Api_Action($event->getApp()));
AddListener(ContainerBuildEvent::NAME, fn(ContainerBuildEvent $event) => Container_Build($event->getBuilder()));

// Dompdf
AddListener(ConfigurationEvent::NAME, function (ConfigurationEvent $event) {
    $event->import([
        "PDF_BACKEND" => "CPDF",
        "PDF_STYLESHEET_FILENAME" => "css/ewpdf.css", // Export PDF CSS styles
        "PDF_MEMORY_LIMIT" => "512M", // Memory limit
        "PDF_TIME_LIMIT" => 120, // Time limit
        "PDF_MAX_IMAGE_WIDTH" => 650, // Make sure image width not larger than page width or "infinite table loop" error
        "PDF_MAX_IMAGE_HEIGHT" => 900, // Make sure image height not larger than page height or "infinite table loop" error
        "PDF_IMAGE_SCALE_FACTOR" => 1.53, // Scale factor
    ]);
});
