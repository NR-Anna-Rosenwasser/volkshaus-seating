import Alpine from 'alpinejs';
import seatMap from './seat-map';
import seatSelection from './seat-selection';

Alpine.data('seatMap', seatMap);
Alpine.data('seatSelection', seatSelection);
Alpine.start();
