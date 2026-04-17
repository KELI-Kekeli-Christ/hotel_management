<!DOCTYPE html>
<html>
<head>
    <title>Nouveau Message de Contact</title>
</head>
<body>
    <h2>Vous avez reçu un nouveau message depuis le formulaire de contact du site.</h2>
    <p><strong>Nom :</strong> {{ $data['name'] }}</p>
    <p><strong>Email :</strong> {{ $data['email'] }}</p>
    <p><strong>Sujet :</strong> {{ $data['subject'] }}</p>
    <hr>
    <p><strong>Message :</strong></p>
    <p>{{ $data['message'] }}</p>
</body>
</html>
{{-- 
    This file is part of the Hotel Management System project.
    It is used to send an email when a contact form is submitted.
    @package Hotel Management System
    @version 1.0
    @author SamLams & Christ
    @license MIT License
--}}