import React, { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import Axios from '../../providers/request';

import { Typography } from '@mui/material';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import LoadingButton from '@mui/lab/LoadingButton';
import InputAdornment from '@mui/material/InputAdornment';
import { HStack } from '../../components/Base';
import { Slider } from '../../components/Part';

import StarIcon from '@mui/icons-material/Star';
import SearchIcon from '@mui/icons-material/Search';
import AddCircleOutlineIcon from '@mui/icons-material/AddCircleOutline';

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

    const getProvider = async (init) => {
        let rdata = await Axios('POST', '/get_provider', {});
        if (rdata) {
            let data = rdata;
            if (init === 'all') {
                setActPro('all');
                setProviderName('All');
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
            setProviders(data);
            setAllcount(c);
        }
    }

    const getGames = async (id, page) => {
        setLoading(true);
        let rdata = await Axios('POST', '/get_casino_game', { id, page });
        if (page === 0) {
            setGames(rdata);
        } else {
            setGames([...games, ...rdata]);
        }
        setLoading(false);
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
        <Box sx={{ pt: 2 }}>
            <Grid container spacing={2}>
                <Grid item xs={2.2}>
                    <Stack className='sports-list' sx={{ px: 0 }}>
                        <Box className='sports-search-wrap' sx={{ px: '8px !important' }}>
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
                        </Box>
                        <Box className='sports-list-body'>
                            <Box className='list-item'>
                                <Box className='item-btn-wrap'>
                                    <Box sx={{ mx: 1, borderBottom: '1px solid #262e4880' }}>
                                        <Button
                                            sx={{ pl: 2, '& .MuiButton-endIcon': { mr: 0, ml: 'auto' } }}
                                            startIcon={<Box component='img' src={`${location.origin}/frontend/Default/img/svg/casino.svg`} alt='all' sx={{ width: 25, maxHeight: 30 }} />}
                                            endIcon={<Typography component='span' sx={{ fontSize: '12px !important' }}>{allcount}</Typography>}
                                            className={classNames('sports-list-item-btn', 'btn', { 'active': actPro === 'all' })}
                                            onClick={() => setGameProvider('all')}
                                        >
                                            <Typography component='span' sx={{ fontSize: '14px' }}>
                                                All Games
                                            </Typography>
                                        </Button>
                                    </Box>
                                </Box>
                            </Box>

                            {
                                providers.map((item, idx) => (
                                    <Box className='list-item' key={idx}>
                                        <Box className='item-btn-wrap'>
                                            <Box sx={{ mx: 1, borderBottom: '1px solid #262e4880' }}>
                                                <Button
                                                    sx={{ pl: 2, '& .MuiButton-endIcon': { mr: 0, ml: 'auto' } }}
                                                    startIcon={<Box component='img' src={`/1wrri/providers/small/${item.href}.svg`} alt={item.href} sx={{ width: 25, maxHeight: 30 }} />}
                                                    endIcon={<Typography component='span' sx={{ fontSize: '12px !important' }}>
                                                        {item.count}
                                                    </Typography>}
                                                    className={classNames('sports-list-item-btn', 'btn', { 'active': actPro.category_id === item.category_id })}
                                                    onClick={() => setGameProvider(item)}
                                                >
                                                    <Typography component='span' sx={{ fontSize: '14px' }}>
                                                        {item.title}
                                                    </Typography>
                                                </Button>
                                            </Box>
                                        </Box>
                                    </Box>
                                ))
                            }
                        </Box>
                    </Stack>
                </Grid>
                <Grid item xs={9.8}>
                    <Box className='casino'>
                        <HStack >
                            <Box sx={{ width: '100%' }}>
                                <Stack className='casino-top' sx={{ px: 2, pb: 2 }}>
                                    <HStack className='casino-top-title'>
                                        <HStack alignItems='center'>
                                            <Box className='title-separator' />
                                            <StarIcon sx={{ fontSize: '16px', color: 'gold', margin: '0 1vw' }} />
                                        </HStack>
                                        <Typography varient='h1' className='casino-title-name'>Jackpot</Typography>
                                        <HStack alignItems='center'>
                                            <StarIcon sx={{ fontSize: '16px', color: 'gold', margin: '0 1vw' }} />
                                            <Box className='title-separator' sx={{ transform: 'rotate(180deg);' }} />
                                        </HStack>
                                    </HStack>
                                    <Typography varient='h1' className='casino-get-price'>208599$</Typography>
                                    <HStack sx={{ mt: 'auto', minHeight: '26%', width: '100%' }}>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${games[0] && games[0].name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} />
                                                {/* <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" /> */}
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${games[1] && games[1].name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} />
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${games[2] && games[2].name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} />
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${games[3] && games[3].name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} />
                                            </Box>
                                        </Box>
                                    </HStack>
                                </Stack>
                            </Box>
                            <Box sx={{ width: '65%', height: '100%', ml: 2 }}>
                                <Slider />
                            </Box>
                        </HStack>
                        <Box sx={{ mt: 3 }}>
                            <HStack sx={{ mb: 2 }}>
                                <Typography sx={{ fontSize: 18, fontWeight: 600 }}>
                                    {providerName}
                                </Typography>
                            </HStack>
                            <Grid container spacing={2}>
                                {
                                    games.map((item, idx) => (
                                        <Grid item xs={2} key={idx}>
                                            <Box className="game-card">
                                                <Box className="game-card-image-container">
                                                    <Box sx={{ backgroundImage: `url(/frontend/Default/ico/${item.name}.jpg)`, backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(item)} />
                                                    {/* <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" /> */}
                                                </Box>
                                            </Box>
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
                                                loading ? 'Loading' : 'Load More'
                                            }
                                        </LoadingButton>
                                }
                            </HStack>
                        </Box>
                    </Box>
                </Grid>
            </Grid>
        </Box >
    )
};

export default Casino;