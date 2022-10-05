import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import BetSlip from '../../components/Betslip';
import { Slider, SportList, Tournament, Event } from '../../components/Part';

const Live = () => {
    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportList />
                </Grid>
                <Grid item xs={7.6}>
                    <Box>
                        <Slider />
                    </Box>
                    <Box sx={{ mt: 2 }}>
                        <Tournament />
                    </Box>
                </Grid>
                <Grid item xs={2.2}>
                    <BetSlip />
                </Grid>
            </Grid>
        </Box>
    )
};

export default Live;