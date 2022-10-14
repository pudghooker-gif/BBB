
import { BrowserRouter } from 'react-router-dom';
import { useDispatch, useSelector } from 'react-redux';
import { useEffect } from 'react';
import Routes from './routes';
import { pre, getSports, } from './state/requests';

function App() {
    const dispatch = useDispatch();
    const { isLive, scale } = useSelector(state => state.sports);

    const realSport = () => {
        dispatch(getSports({ isLive, ...scale }));
    }

    useEffect(() => {
        dispatch(pre());
        // const RSSI = setInterval(realSport, 20000);
        // return () => clearInterval(RSSI);
    }, []);

    return (
        <BrowserRouter basename="">
            <Routes />
        </BrowserRouter>
    );
}

export default App;