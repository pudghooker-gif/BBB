import React, { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

import { Typography } from '@mui/material';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import LoadingButton from '@mui/lab/LoadingButton';
import InputAdornment from '@mui/material/InputAdornment';

import { HStack } from '../../components/Base';
import { Slider } from '../../components/Part';

import StarIcon from '@mui/icons-material/Star';
import SearchIcon from '@mui/icons-material/Search';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import AddCircleOutlineIcon from '@mui/icons-material/AddCircleOutline';

import top from '../../assets/img/feature/top.svg'
import all from '../../assets/img/feature/all-game.svg'

import classNames from 'classnames';

const Casino = () => {
    const navigate = useNavigate();
    const user = useSelector((state) => state.user);

    const [providers, setProviders] = useState([]);
    const [games, setGames] = useState([]);
    const [actPro, setActPro] = useState();
    const [page, setPage] = useState(0);
    const [loading, setLoading] = useState(false);
    const [providerName, setProviderName] = useState();
    const [allcount, setAllcount] = useState();

    const getProvider = (init) => {
        axios.post('/get_provider', {})
            .then(
                response => {
                    let data = response.data;
                    setProviders(data);
                    if (init === 'all') {
                        setActPro('all');
                        setProviderName('All')
                        getGames('all', page);
                    }
                    let c = 0;
                    for (let item of data) {
                        if (item.href === init) {
                            setActPro(item);
                            setProviderName(item.title);
                            getGames(item.category_id, page);
                        }
                        c += item.count;
                    }
                    setAllcount(c);
                }
            )
            .catch(error => {
                console.log("ERROR:: ", error.response.data);
            });
    }

    const getGames = (id, page) => {
        setLoading(true);
        axios.post('/get_casino_game', { id, page })
            .then(
                response => {
                    if (page === 0) {
                        setGames(response.data);
                    } else {
                        setGames([...games, ...response.data]);
                    }
                    setLoading(false);
                }
            )
            .catch(error => {
                console.log("ERROR:: ", error.response.data);
                setLoading(false);
            });
    }

    const setGameProvider = (item) => {
        if (item === 'all') {
            setActPro(item);
            setProviderName('All')
            getGames('all', 0);
            navigate('/casino/all');
        } else {
            setActPro(item);
            setProviderName(item.title)
            getGames(item.category_id, 0);
            navigate(`/casino/${item.href}`);
        }
        setPage(0);
    }

    const loadMore = () => {
        getGames(actPro.category_id ? actPro.category_id : 'all', page + 1);
        setPage(page + 1);
    }

    const goGame = (item) => {
        if (user.isAuth) {
            window.open(`${location.origin}/${item.name}/?api_exit=/`, "_blank");
        } else {
            document.getElementsByClassName('login_btn ')[0].click();
        }
    }

    useEffect(() => {
        let param = location.pathname.split('/')[2];
        getProvider(param);
    }, [])

    return (
        <Box sx={{ mt: 1 }}>
            <HStack >
                <Slider />
            </HStack>
            <Box sx={{ mt: 1 }}>
                <HStack className='top-casino'>
                    <HStack sx={{ height: '100%', alignItems: 'center' }}>
                        <Box className='left-red' />
                        <Box sx={{ position: 'relative' }}>
                            <Box component='img' src={top} sx={{ filter: 'blur(10px) saturate(2)', mr: 1 }} />
                            <Box component='img' src={top} sx={{ position: 'absolute', left: 0, top: '3px' }} />
                        </Box>
                        <Stack>
                            <Typography sx={{ fontSize: 13, fontWeight: 600 }}>Top games</Typography>
                            <Typography sx={{ fontSize: 10, color: '#94a6cd', opacity: .7 }}>139 games</Typography>
                        </Stack>
                    </HStack>
                    <ChevronRightIcon />
                </HStack>
            </Box>
            <Box sx={{ my: 1 }}>
                <Stack className='casino-bg' sx={{ px: 1, pb: 1, pt: 1.25, borderRadius: '10px' }}>
                    <TextField
                        variant="outlined"
                        className='casino-search'
                        placeholder='Search'
                        InputProps={{
                            startAdornment: (
                                <InputAdornment position="start">
                                    <SearchIcon />
                                </InputAdornment>
                            ),
                        }}
                    />
                    <Select
                        // value={age}
                        // onChange={handleChange}
                        // displayEmpty
                        sx={{
                            'fieldset': { display: 'none ' },
                            px: 1.25,
                            mt: 1,
                            height: 35,
                            borderRadius: 2,
                            bgcolor: '#97aee117',
                            width: '100%'
                        }}
                    >
                        <MenuItem value="">Provider</MenuItem>
                        <MenuItem value={10}>Ten</MenuItem>
                        <MenuItem value={20}>Twenty</MenuItem>
                        <MenuItem value={30}>Thirty</MenuItem>
                    </Select>
                </Stack>
            </Box>
            <Box>
                <Box sx={{ mt: .5 }}>
                    <HStack className='all-casino'>
                        <HStack sx={{ height: '100%', alignItems: 'center' }}>
                            <Box className='left-red' />
                            <Box sx={{ position: 'relative' }}>
                                <Box component='img' src={all} sx={{ filter: 'blur(10px) saturate(2)', mr: 1 }} />
                                <Box component='img' src={all} sx={{ position: 'absolute', left: 0, top: '3px' }} />
                            </Box>
                            <Stack>
                                <Typography sx={{ fontSize: 13, fontWeight: 600 }}>All games</Typography>
                                <Typography sx={{ fontSize: 10, color: '#94a6cd', opacity: .7 }}>139 games</Typography>
                            </Stack>
                        </HStack>
                    </HStack>
                </Box>
                <Box className='casino-bg' sx={{ padding: 2 }}>
                    <Box className='all-effect' />
                    <Grid container spacing={2}>
                        {
                            games.map((item, idx) => (
                                <Grid item xs={6} key={idx}>
                                    <Box className="game-card">
                                        <Box className="game-card-image-container">
                                            <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${item.name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(item)} />
                                        </Box>
                                    </Box>
                                    <HStack justifyContent='space-between'>
                                        <Typography sx={{ fontSize: 13, overflow: 'hidden', textOverflow: 'ellipsis', WebkitBoxOrient: 'vertical' }}>{item.name}</Typography>
                                        <StarIcon sx={{ fontSize: 18 }} />
                                    </HStack>
                                </Grid>
                            ))
                        }
                    </Grid>
                    <HStack justifyContent='center' sx={{ mt: 2 }}>
                        {
                            actPro && actPro.count && actPro.count < (page + 1) * 12 ? null :
                                <LoadingButton
                                    size="large"
                                    sx={{ borderRadius: 2, color: 'white', fontWeight: 700, textTransform: 'capitalize' }}
                                    className='able'
                                    onClick={loadMore}
                                    endIcon={<AddCircleOutlineIcon sx={{ color: 'white' }} />}
                                    loading={loading}
                                    loadingPosition="end"
                                    variant="contained"
                                >
                                    {
                                        loading ? 'Loading' : 'Show More'
                                    }
                                </LoadingButton>
                        }
                    </HStack>
                </Box>
            </Box>
        </Box>
    )
};

export default Casino;