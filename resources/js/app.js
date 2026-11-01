import { bindDetailsControls } from './details';
import { bindPushToggle, initializePushNotifications } from './push';
import { bindRefreshControls, initializeRefresh } from './refresh';

bindDetailsControls();
bindRefreshControls();
bindPushToggle();
initializePushNotifications();
initializeRefresh();
