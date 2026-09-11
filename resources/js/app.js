import './bootstrap';
import './echo';

import Alpine from 'alpinejs';
import connectionStatus from './components/connection-status';
import conversationList from './components/conversation-list';
import conversationThread from './components/conversation-thread';
import inviteForm from './components/invite-form';

Alpine.data('connectionStatus', connectionStatus);
Alpine.data('inviteForm', inviteForm);
Alpine.data('conversationList', conversationList);
Alpine.data('conversationThread', conversationThread);

window.Alpine = Alpine;
Alpine.start();
