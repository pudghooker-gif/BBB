import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import { Slider } from '../../components/Part';
import BetSlip from '../../components/Betslip';
import SportsListNav from '../../components/SportsListNav';
import SportsLeagueContent from '../../components/SportsLeagueContent';

const DesktopLive = () => {
    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportsListNav isLive={true} />
                </Grid>
                <Grid item xs={7.6}>
                    <Box>
                        <Slider />
                    </Box>
                    <Box sx={{ mt: 2 }}>
                        <SportsLeagueContent isPre={false} />
                    </Box>
                </Grid>
                <Grid item xs={2.2}>
                    <BetSlip />
                </Grid>
            </Grid>
        </Box>
    )
};

export default DesktopLive;