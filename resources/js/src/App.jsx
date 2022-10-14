
import { BrowserRouter } from 'react-router-dom';
import { useDispatch } from 'react-redux';
import { useEffect } from 'react';
import Routes from './routes';
import { pre } from './redux/action/user/actions';

function App() {
    const dispatch = useDispatch();

    useEffect(() => {
        dispatch(pre());
    }, []);

    return (
        <BrowserRouter basename="">
            <Routes />
        </BrowserRouter>
    );
}

export default App;