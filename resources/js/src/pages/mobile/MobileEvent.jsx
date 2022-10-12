import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import BetSlip from '../../components/Betslip';
import { Event } from '../../components/Part';

const EventMatch = () => {
    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                </Grid>
                <Grid item xs={7.6}>
                    <Box>
                        <Event />
                    </Box>
                </Grid>
                <Grid item xs={2.2}>
                    <BetSlip />
                </Grid>
            </Grid>
        </Box>
    )
};

export default EventMatch;