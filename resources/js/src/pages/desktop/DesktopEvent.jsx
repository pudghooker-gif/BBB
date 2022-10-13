import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import BetSlip from '../../components/Betslip';
import SportsEvent from '../../components/SportsEvent';
import SportsListNav from '../../components/SportsListNav';

const EventMatch = () => {
    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportsListNav />
                </Grid>
                <Grid item xs={7.6}>
                    <Box>
                        <SportsEvent />
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