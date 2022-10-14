import reportWebVitals from './reportWebVitals';
import MuiThemeProvider from './theme';
import configureStore from '../redux/store';
import { Provider } from 'react-redux';
import { ToastProvider } from 'react-toast-notifications';
const store = configureStore();
export {
    store,
    Provider,
    ToastProvider,
    reportWebVitals,
    MuiThemeProvider,
};