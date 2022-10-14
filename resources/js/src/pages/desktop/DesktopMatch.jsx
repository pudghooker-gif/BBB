import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import BetSlip from '../../components/Betslip';
import { Slider } from '../../components/Part';
import SportsListNav from '../../components/SportsListNav';
import SportsLeagueContent from '../../components/SportsLeagueContent';

const Prematch = () => {
    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportsListNav />
                </Grid>
                <Grid item xs={7.6}>
                    <Box>
                        <Slider />
                    </Box>
                    <Box sx={{ mt: 2 }}>
                        <SportsLeagueContent />
                    </Box>
                </Grid>
                <Grid item xs={2.2}>
                    <BetSlip />
                </Grid>
            </Grid>
        </Box>
    )
};

export default Prematch;