import React, { useEffect } from 'react';

import { useDispatch, useSelector } from 'react-redux';

import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import { Slider } from '../../components/Part';
import BetSlip from '../../components/Betslip';
import SportsEvent from '../../components/SportsEvent';
import SportsListNav from '../../components/SportsListNav';
import SportsLeagueContent from '../../components/SportsLeagueContent';

import { saveIsEvent } from '../../state/sports/actions';


const DesktopLive = () => {
    const dispatch = useDispatch();
    const { isEvent } = useSelector(state => state.sports);

    const switchPage = () => {
        let path = location.pathname.split('/');
        if (path[6]) {
            dispatch(saveIsEvent(true));
        } else {
            dispatch(saveIsEvent(false));
        }
    }

    useEffect(() => {
        switchPage();
    }, [location.pathname]);

    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportsListNav isLive={true} />
                </Grid>
                {
                    isEvent ?
                        <Grid item xs={7.6}>
                            <Box>
                                <SportsEvent />
                            </Box>
                        </Grid> :
                        <Grid item xs={7.6}>
                            <Box>
                                <Slider />
                            </Box>
                            <Box sx={{ mt: 2 }}>
                                <SportsLeagueContent />
                            </Box>
                        </Grid>
                }
                <Grid item xs={2.2}>
                    <BetSlip />
                </Grid>
            </Grid>
        </Box>
    )
};

export default DesktopLive;