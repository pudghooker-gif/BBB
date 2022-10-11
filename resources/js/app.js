import {
    store,
    Provider,
    ToastProvider,
    MuiThemeProvider
} from './src/providers';

import ReactDOM from 'react-dom'
import App from './src/App';

ReactDOM.render(
    <Provider store={store}>
        <MuiThemeProvider>
            <ToastProvider
                autoDismiss
                autoDismissTimeout={6000}
                //  components={{ Toast: Snack }}
                placement="top-right"
            >
                <App />
            </ToastProvider>
        </MuiThemeProvider>
    </Provider>,
    document.getElementById('app')
)