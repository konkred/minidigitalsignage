<?php
return [
    // Zeitzone für die Eingabe und Anzeige geplanter Änderungen.
    'timezone' => 'Europe/Berlin',
    // Ohne Lebenszeichen seit so vielen Sekunden gilt ein Monitor als offline.
    'online_after' => 20,
    // Erst nach so vielen Sekunden offline geht eine Benachrichtigung raus.
    'notify_after' => 300,
    // Absender der Benachrichtigungen. Leer: noreply@ und die Domain der Installation.
    'mail_from' => '',
];
