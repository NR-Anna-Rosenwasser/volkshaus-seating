@php
    $seated = $results->filter->isSeated();
    $unseated = $results->reject->isSeated();
    $split = $seated->filter->split;
@endphp

<div class="admin-preview">
    @unless ($deadlinePassed)
        <p class="admin-notice">Die Frist läuft noch. Ausführen geht erst nach der Frist, bis dahin können alle selbst wählen.</p>
    @endunless

    <p>
        Noch ist nichts geändert. {{ $seated->count() }} Bestellungen bekämen {{ $seated->sum('missing') }} Plätze,
        {{ $split->count() }} davon aufgeteilt. {{ $unseated->count() }} Bestellungen bekämen keinen Platz.
    </p>

    @if ($results->isNotEmpty())
        <div class="admin-table">
            <table>
                <thead>
                    <tr><th>Bestellung</th><th>Name</th><th>Tickets</th><th>Plätze</th></tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr @class(['is-problem' => ! $result->isSeated()])>
                            <td>{{ $result->order->short_id }}</td>
                            <td>{{ $result->order->first_name }} {{ $result->order->last_name }}</td>
                            <td>{{ $result->missing }}</td>
                            <td>
                                {{ $result->isSeated() ? implode(', ', $result->seatIds) : 'Kein Platz' }}
                                @if ($result->split)
                                    <strong>(aufgeteilt)</strong>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
