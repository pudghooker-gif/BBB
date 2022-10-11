import {
  Root,
  store,
  Provider,
  ToastProvider,
  MuiThemeProvider
} from './providers';

import App from './App';
Root.render(
  <Provider store={store}>
    <MuiThemeProvider>
      <ToastProvider
        autoDismiss
        autoDismissTimeout={6000}
        //  components={{ Toast: Snack }}
        placement="bottom-center"
      >
        <App />
      </ToastProvider>
    </MuiThemeProvider>
  </Provider>
);

// reportWebVitals();
