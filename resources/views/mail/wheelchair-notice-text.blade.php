@if ($cancelled)
Eine Bestellung mit Rollstuhlplatz wurde storniert. Bereits zugeteilte Rollstuhlplätze sind wieder gesperrt.
@else
Jemand hat beim Ticketkauf einen Rollstuhlplatz angekreuzt. Alle Tickets der Bestellung sind erfasst, aber sie bekommt keine Mails zur Platzwahl und wird nicht automatisch zugeteilt. Bitte meldet euch bei der Person und teilt die Plätze von Hand zu.
@endif

Name: {{ $order->first_name }} {{ $order->last_name }}
E-Mail: {{ $order->email }}
Bestellung: {{ $order->short_id }}
Aktive Tickets in der Bestellung: {{ $tickets }}
