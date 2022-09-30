import { Suspense, LazyExoticComponent, ComponentType } from 'react';
import { LinearProgressProps } from '@mui/material/LinearProgress';
import Loader from './Loader';

const Loadable =
    (Component) =>
        (props) =>
        (
            <Suspense fallback={<Loader />}>
                <Component {...props} />
            </Suspense>
        );

export default Loadable;
