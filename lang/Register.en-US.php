Subject: Registration Information
From: <?= $From ?>
To: <?= $To ?>
Cc:
Bcc:
Format: HTML

<p>Dear Sir/Madam,</p>

<p>Thank you for registering. Your information is as follow:</p>

<?php foreach ($Fields as $fieldName => $field) { ?>
<p><?= $field->caption ?>: <?= $field->value ?></p>
<?php } ?>

<?php if (@$ActivateLink) { ?>
<p>Please click the following link to activate your account:<br>
<a href="<?= $ActivateLink ?>">Activate account</a>
</p>
<?php } ?>

<p>Please feel free to contact us in case of further queries.</p>

<p>
Best Regards,<br>
Support
</p>
