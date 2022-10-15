import React, { useEffect, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import Axios from '../../providers/request';


import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';

import { Slider } from '../../components/Part';
import BetSlip from '../../components/Betslip';
import SportsEvent from '../../components/SportsEvent';
import SportsListNav from '../../components/SportsListNav';
import SportsLeagueContent from '../../components/SportsLeagueContent';

const DesktopLive = () => {
    const [old, setOld] = useState({});
    const [scale, setScale] = useState({});
    const [sportsList, setSportsList] = useState([]);
    const [sportsMatchs, setSportsMatchs] = useState([]);

    const getSportsLists = async (flag) => {
        let rdata = await Axios('post', '/sports/get_sports', { isLive: flag });
        setSportsList(rdata);
    }

    const getSportMatchs = async (data) => {
        let rdata = await Axios('post', '/sports/get_sports_data', data);
        updateMatchs(rdata, old, setOld, setSportsMatchs);
    }

    const getSportMatchsTimer = async () => {
        let rdata = await Axios('post', '/sports/get_sports_data', scale);
        updateMatchs(rdata, old, setOld, setSportsMatchs);
    }

    const updateMatchs = (matchs, old, setOld, setSportsMatchs) => {
        let newOld = {};
        if (matchs.length) {
            for (const i in matchs) {
                matchs[i].home = JSON.parse(matchs[i].home);
                matchs[i].away = JSON.parse(matchs[i].away);
                matchs[i].league = JSON.parse(matchs[i].league);
                matchs[i].odds = JSON.parse(matchs[i].odds);
                matchs[i].timer = JSON.parse(matchs[i].timer);
                matchs[i].scores = JSON.parse(matchs[i].scores);

                if (old[matchs[i].id]) {
                    if (matchs[i].odds && old[matchs[i].id].odds) {
                        for (let mk in matchs[i].odds) {
                            if (matchs[i].odds[mk] && old[matchs[i].id].odds[mk]) {
                                let mId = mk.slice(-1);
                                let oldMk = old[matchs[i].id].odds[mk];
                                let newMk = matchs[i].odds[mk];
                                let hc = 0;
                                switch (mId) {
                                    case '1':
                                    case '2':
                                    case '5':
                                    case '8':
                                        if (Number(newMk['home_od']) && Number(oldMk['home_od'])) {
                                            hc = Number(newMk['home_od']) - Number(oldMk['home_od']);
                                            matchs[i].odds[mk]['home_change'] = hc > 0 ? 'up' : hc < 0 ? 'down' : '';
                                        }
                                        if (Number(newMk['draw_od']) && Number(oldMk['draw_od'])) {
                                            hc = Number(newMk['draw_od']) - Number(oldMk['draw_od']);
                                            matchs[i].odds[mk]['draw_change'] = hc > 0 ? 'up' : hc < 0 ? 'down' : '';
                                        }
                                        if (Number(newMk['away_od']) && Number(oldMk['away_od'])) {
                                            hc = Number(newMk['away_od']) - Number(oldMk['away_od']);
                                            matchs[i].odds[mk]['away_change'] = hc > 0 ? 'up' : hc < 0 ? 'down' : '';
                                        }
                                        break;
                                    case '3':
                                    case '4':
                                    case '6':
                                    case '7':
                                        if (Number(newMk['over_od']) && Number(oldMk['over_od'])) {
                                            hc = Number(newMk['over_od']) - Number(oldMk['over_od']);
                                            matchs[i].odds[mk]['over_change'] = hc > 0 ? 'up' : hc < 0 ? 'down' : '';
                                        }
                                        if (Number(newMk['under_od']) && Number(oldMk['under_od'])) {
                                            hc = Number(newMk['under_od']) - Number(oldMk['under_od']);
                                            matchs[i].odds[mk]['under_change'] = hc > 0 ? 'up' : hc < 0 ? 'down' : '';
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
                newOld[matchs[i].id] = matchs[i];
            }
            setOld(newOld);
            setSportsMatchs(matchs);
        } else {
            setSportsMatchs(matchs);
        };
    }

    const init = () => {
        let scale = {}, path = location.pathname.split('/');
        let checkLive = path[2] === 'live';

        scale = { ...scale, isLive: checkLive };

        if (path[3]) {
            scale = { ...scale, sportId: Number(path[3]) };
        } else {
            scale = { ...scale, sportId: '' };
        }

        if (path[4]) {
            scale = { ...scale, country: path[4] === 'world' ? 'null' : path[4] };
        } else {
            scale = { ...scale, country: '' };
        }

        if (path[5]) {
            scale = { ...scale, leagueId: path[5] };
        } else {
            scale = { ...scale, leagueId: '' };
        }

        if (path[6]) {
            scale = { ...scale, isEvent: true };
        } else {
            scale = { ...scale, isEvent: false };
        }

        setScale(scale);
        getSportsLists(scale.isLive);
        getSportMatchs({ ...scale });
    };

    useEffect(() => {
        init();
    }, [location.pathname]);


    // useEffect(() => {
    //     let unmounted = false;
    //     if (!unmounted) {
    //         getSportsLists();
    //     }
    //     return () => {
    //         unmounted = true;
    //     };
    // }, []);

    useEffect(() => {
        let unmounted = false;
        const timer = setInterval(() => {
            if (!unmounted) {
                getSportMatchsTimer();
                // getSportsListsTimer();
            }
        }, 10000);
        return () => {
            clearInterval(timer);
            unmounted = true;
        };
    }, [getSportMatchsTimer]);

    return (
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <SportsListNav {...{ data: sportsList, scale, setScale, init }} />
                </Grid>
                {
                    scale.isEvent ?
                        <Grid item xs={7.6}>
                            <Box>
                                <SportsEvent {...{ data: sportsMatchs, scale }} />
                            </Box>
                        </Grid> :
                        <Grid item xs={7.6}>
                            <Box>
                                <Slider />
                            </Box>
                            <Box sx={{ mt: 2 }}>
                                <SportsLeagueContent {...{ data: sportsMatchs, init }} />
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