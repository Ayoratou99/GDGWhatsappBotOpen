import './bootstrap';
import './echo';

import Alpine from 'alpinejs';
import conversationList from './components/conversation-list';
import conversationThread from './components/conversation-thread';

Alpine.data('conversationList', conversationList);
Alpine.data('conversationThread', conversationThread);

window.Alpine = Alpine;
Alpine.start();
