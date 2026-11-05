@if ($cancelled)
Eine Bestellung mit Rollstuhlplatz wurde ganz oder teilweise storniert. Bereits zugeteilte Rollstuhlplätze sind wieder gesperrt.
@else
Jemand hat beim Ticketkauf einen Rollstuhlplatz angekreuzt. Diese Bestellung bekommt keine Mail zur Platzwahl und wird nicht automatisch zugeteilt. Bitte meldet euch bei der Person.
@endif

Name: {{ $order->first_name }} {{ $order->last_name }}
E-Mail: {{ $order->email }}
Bestellung: {{ $order->short_id }}
Aktive Tickets in der Bestellung: {{ $tickets }}
Rollstuhlplatz angekreuzt für: {{ $wheelchairAttendees->join(', ') }}
