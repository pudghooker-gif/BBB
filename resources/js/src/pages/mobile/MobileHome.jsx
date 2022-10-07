import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import moment from 'moment';
import axios from 'axios';
import classNames from 'classnames';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Pagination, Autoplay } from 'swiper';

import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import Link from '@mui/material/Link';
import Table from '@mui/material/Table';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import TableRow from '@mui/material/TableRow';
import TableHead from '@mui/material/TableHead';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import Typography from '@mui/material/Typography';

import AccessTimeIcon from '@mui/icons-material/AccessTime';

import { Clock } from '../../assets/img/feature/svgIcon';
import { Slider } from '../../components/Part';
import { HStack, VStack } from '../../components/Base';

import popularIcon from '../../assets/img/feature/fire.svg';
import qatar from '../../assets/img/feature/qatar.svg';
import timerWing from '../../assets/img/feature/timer-wing.svg';
import timerSpace from '../../assets/img/feature/timer-space.svg';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

const Home = () => {
    const navigate = useNavigate();
    const dispatch = useDispatch();
    const user = useSelector((state) => state.user);
    const sportsList = ['Soccer', 'Basketball', 'Cricket', 'Table Tennis', 'American Football', 'Tennis'];
    const [actLiveItem, setActLiveItem] = useState(0);
    const [actSport, setActSport] = useState(0);
    const [timer, setTimer] = useState({});
    const [casino, setCasino] = useState([]);

    const goBonus = () => {
        if (user.isAuth) {
            console.log('bonus');
        } else {
            document.getElementsByClassName('register_btn')[0].click();
        }
    }

    const numberFormat = (e) => {
        e = String(e);
        return e.length == 1 ? `0${e}` : e;
    }

    const countDown = () => {
        const now = new Date(moment(new Date()).utcOffset('GMT-03:00').format()).getTime();
        const expiration = new Date('2022-11-20 00:00').getTime();
        const diff = expiration - now + (3600 * 12 * 1000);
        const day = numberFormat(Math.ceil(diff / (1000 * 3600 * 24)));
        const mod = diff % (1000 * 3600 * 24);
        const hour = numberFormat(Math.ceil(mod / (1000 * 3600)));
        const mod1 = mod % (1000 * 3600);
        const minute = numberFormat(Math.floor(mod1 / (1000 * 60)));
        const mod2 = mod1 % (1000 * 60);
        const second = numberFormat(Math.floor(mod2 / 1000));
        setTimer({ day, hour, minute, second });
    }

    const countStart = () => {
        countDown();
        setInterval(countDown, 1000)
    }

    const getCasino = () => {
        axios.post('/home_casino', {})
            .then(
                response => {
                    let data = response.data;
                    setCasino(data);
                }
            )
            .catch(error => {
                console.log("ERROR:: ", error.response.data);
            });
    }

    const goGame = (item) => {
        if (user.isAuth) {
            window.open(`${location.origin}/${item.name}/?api_exit=/`, "_blank");
        } else {
            document.getElementsByClassName('login_btn ')[0].click();
        }
    }

    useEffect(() => {
        getCasino();
        countStart();
    }, []);

    return (
        <Stack>
            <HStack sx={{ py: 2, height: 200 }}>
                <Slider />
            </HStack>
            <Box>
                <HStack
                    sx={{
                        px: 1,
                        py: 1,
                        borderRadius: 4,
                        alignItems: 'center',
                        bgcolor: 'rgb(36,0,0)',
                        background: 'linear-gradient(90deg, rgba(36,0,16,0.6138830532212884) 0%, rgba(121,9,26,0.7315301120448179) 69%, rgba(255,0,74,1) 100%)'
                    }}>
                    <Box component='img' src={qatar} sx={{ height: 60 }} />
                    <HStack
                        sx={{
                            alignItems: 'center',
                            justifyContent: 'space-around',
                            width: '75%',
                            ml: 'auto'
                        }}>
                        <Stack alignItems='center' sx={{ mx: 1 }}>
                            <Typography varient='h1' sx={{ fontSize: 20, lineHeight: 1.2, fontWeight: 600 }}>{timer.day}</Typography>
                            <Typography sx={{ fontSize: 10 }}>Days</Typography>
                        </Stack>
                        <Box component='img' src={timerSpace} sx={{ width: 10 }} />
                        <Stack alignItems='center' sx={{ mx: 1 }}>
                            <Typography varient='h1' sx={{ fontSize: 20, lineHeight: 1.2, fontWeight: 600 }}>{timer.hour}</Typography>
                            <Typography sx={{ fontSize: 10 }}>Hours</Typography>
                        </Stack>
                        <Box component='img' src={timerSpace} sx={{ width: 10 }} />
                        <Stack alignItems='center' sx={{ mx: 1 }}>
                            <Typography varient='h1' sx={{ fontSize: 20, lineHeight: 1.2, fontWeight: 600 }}>{timer.minute}</Typography>
                            <Typography sx={{ fontSize: 10 }}>Minutes</Typography>
                        </Stack>
                        <Box component='img' src={timerSpace} sx={{ width: 10 }} />
                        <Stack alignItems='center' sx={{ mx: 1 }}>
                            <Typography varient='h1' sx={{ fontSize: 20, lineHeight: 1.2, fontWeight: 600 }}>{timer.second}</Typography>
                            <Typography sx={{ fontSize: 10 }}>Seconds</Typography>
                        </Stack>
                    </HStack>
                </HStack>
            </Box>
            <HStack justifyContent='space-between' sx={{ my: 1 }}>
                <Typography sx={{ fontSize: 19, fontWeight: 700 }}>Top Live</Typography>
                <Stack>
                    <Typography sx={{ color: '#0085ff', fontSize: 13, fontWeiht: 500, textAlign: 'right' }}>All</Typography>
                    <Typography sx={{ color: '#94a6cd', fontSize: 11, textAlign: 'right' }}>21 events</Typography>
                </Stack>
            </HStack>
            <Box sx={{ mx: -2 }} >
                <Stack sx={{ pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Autoplay]}
                        slidesPerView={1.1}
                        spaceBetween={10}
                        className='popular-events'
                    >
                        {
                            [1, 2, 3, 4, 5].map((item, idx) => (
                                <SwiperSlide key={idx}>
                                    <Box className='popular-wrap'>
                                        <Stack className="live-event-main">
                                            <HStack className="match-header">
                                                <Typography component='div' className="top-live-match-score">252:559</Typography>
                                                <Typography component='div' className="match-score-period">(196:219 - 56:340)</Typography>
                                            </HStack>
                                            <Box className="top-live-match-info">
                                                <Box className="match-teams">
                                                    <Stack className="match-team"><Typography component='span' className="helper-line">Warwickshire</Typography></Stack>
                                                    <Stack className="match-team"><Typography component='span' className="helper-line">Warwickshire</Typography></Stack>
                                                </Box>
                                            </Box>
                                            <Box className="match-details">
                                                Cricket · County Championship Division One
                                            </Box>
                                            <HStack className="match-odd-list">
                                                <Box className="match-odd-item">
                                                    <Button className="live-top-odd">
                                                        <HStack className="odd-values">
                                                            <Box className="odd-name">1</Box>
                                                            <Box className="odd-value">5.15</Box>
                                                        </HStack>
                                                    </Button>
                                                </Box>
                                                <Box className="match-odd-item" sx={{ ml: 1 }}>
                                                    <Button className="live-top-odd">
                                                        <HStack className="odd-values">
                                                            <Box className="odd-name">1</Box>
                                                            <Box className="odd-value">5.15</Box>
                                                        </HStack>
                                                    </Button>
                                                </Box>
                                            </HStack>
                                        </Stack>
                                    </Box>
                                </SwiperSlide>
                            ))
                        }
                    </Swiper>
                </Stack>
            </Box>
            <Box >
                <HStack justifyContent='space-between' sx={{ my: 1 }}>
                    <Typography sx={{ fontSize: 19, fontWeight: 700 }}>Seibet games</Typography>
                    <Stack>
                        <Typography sx={{ color: '#0085ff', fontSize: 13, fontWeiht: 500, textAlign: 'right' }}>All</Typography>
                        <Typography sx={{ color: '#94a6cd', fontSize: 11, textAlign: 'right' }}>43</Typography>
                    </Stack>
                </HStack>
                <Stack sx={{ pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Autoplay]}
                        slidesPerView={2.2}
                        spaceBetween={10}
                        className='popular-events'
                    >
                        {
                            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((item, idx) => (
                                <SwiperSlide key={idx}>
                                    <Box className="game-card">
                                        <Box className="game-card-image-container">
                                            <Box sx={{ backgroundImage: casino[item] ? `url(/frontend/Default/ico/${casino[item].name}.jpg)` : '', backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(casino[item])} />
                                        </Box>
                                    </Box>
                                </SwiperSlide>
                            ))
                        }
                    </Swiper>
                </Stack>
            </Box>
            <Box >
                <HStack justifyContent='space-between' sx={{ my: 1 }}>
                    <Typography sx={{ fontSize: 19, fontWeight: 700 }}>Top Casino</Typography>
                    <Stack>
                        <Typography sx={{ color: '#0085ff', fontSize: 13, fontWeiht: 500, textAlign: 'right' }}>All</Typography>
                        <Typography sx={{ color: '#94a6cd', fontSize: 11, textAlign: 'right' }}>43</Typography>
                    </Stack>
                </HStack>
                <Stack sx={{ pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Autoplay]}
                        slidesPerView={2.2}
                        spaceBetween={10}
                        className='popular-events'
                    >
                        {
                            [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22].map((item, idx) => (
                                <SwiperSlide key={idx}>
                                    <Box className="game-card">
                                        <Box className="game-card-image-container">
                                            <Box sx={{ backgroundImage: casino[item] ? `url(/frontend/Default/ico/${casino[item].name}.jpg)` : '', backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(casino[item])} />
                                        </Box>
                                    </Box>
                                </SwiperSlide>
                            ))
                        }
                    </Swiper>
                </Stack>
            </Box>
            <Box >
                <HStack justifyContent='space-between' sx={{ my: 1 }}>
                    <Typography sx={{ fontSize: 19, fontWeight: 700 }}>Table Games</Typography>
                    <Stack>
                        <Typography sx={{ color: '#0085ff', fontSize: 13, fontWeiht: 500, textAlign: 'right' }}>All</Typography>
                        <Typography sx={{ color: '#94a6cd', fontSize: 11, textAlign: 'right' }}>43</Typography>
                    </Stack>
                </HStack>
                <Stack sx={{ pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Autoplay]}
                        slidesPerView={2.2}
                        spaceBetween={10}
                        className='popular-events'
                    >
                        {
                            [23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34].map((item, idx) => (
                                <SwiperSlide key={idx}>
                                    <Box className="game-card">
                                        <Box className="game-card-image-container">
                                            <Box sx={{ backgroundImage: casino[item] ? `url(/frontend/Default/ico/${casino[item].name}.jpg)` : '', backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(casino[item])} />
                                        </Box>
                                    </Box>
                                </SwiperSlide>
                            ))
                        }
                    </Swiper>
                </Stack>
            </Box>
            <Box >
                <HStack justifyContent='space-between' sx={{ my: 1 }}>
                    <Typography sx={{ fontSize: 19, fontWeight: 700 }}>Top Live Casino</Typography>
                    <Stack>
                        <Typography sx={{ color: '#0085ff', fontSize: 13, fontWeiht: 500, textAlign: 'right' }}>All</Typography>
                        <Typography sx={{ color: '#94a6cd', fontSize: 11, textAlign: 'right' }}>43</Typography>
                    </Stack>
                </HStack>
                <Stack sx={{ pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Autoplay]}
                        slidesPerView={2.2}
                        spaceBetween={10}
                        className='popular-events'
                    >
                        {
                            [35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49].map((item, idx) => (
                                <SwiperSlide key={idx}>
                                    <Box className="game-card">
                                        <Box className="game-card-image-container">
                                            <Box sx={{ backgroundImage: casino[item] ? `url(/frontend/Default/ico/${casino[item].name}.jpg)` : '', backgroundRepeat: 'no-repeat', backgroundSize: '100% 100%' }} onClick={() => goGame(casino[item])} />
                                        </Box>
                                    </Box>
                                </SwiperSlide>
                            ))
                        }
                    </Swiper>
                </Stack>
            </Box>
            {/* <Box sx={{ mb: 2 }}>
                <Grid container spacing={2}>
                    {
                        sportsList.map((item, idx) => (
                            <Grid item xs={12 / 7} key={idx}>
                                <Button
                                    onClick={() => setActSport(idx)}
                                    className='middle-item'
                                    sx={{
                                        width: '100%',
                                        height: '100%',
                                        boxShadow: actSport === idx ? '0 0 20px #000000' : 'unset'
                                    }}>
                                    <Box sx={{ mb: 2 }}>
                                        <i className={classNames("sports-icon", `icon-${item.toLocaleLowerCase().replaceAll(' ', '-')}`)} style={{ fontSize: 40, marginTop: '14px', marginBottom: '14px', marginLeft: 'auto', marginRight: 'auto', backgroundColor: actSport !== idx ? '#888fa9' : '', backgroundImage: actSport === idx ? 'linear-gradient(300deg, rgb(118, 200, 245), #005add)' : '' }} />
                                        <Typography
                                            sx={{
                                                zIndex: 1,
                                                position: 'relative',
                                                textTransform: 'capitalize',
                                                color: idx === actSport ? '' : '#888fa9'
                                            }}>
                                            {item}
                                        </Typography>
                                    </Box>
                                </Button>
                            </Grid>
                        ))
                    }
                    <Grid item xs={12 / 7}>
                        <Button
                            onClick={() => navigate('/sports/prematch')}
                            className='middle-item'
                            sx={{
                                width: '100%',
                                height: '100%',
                            }}>
                            <Box sx={{ mb: 2 }}>
                                <AccessTimeIcon style={{ fontSize: 60, margin: '14px' }} />
                                <Typography sx={{ textTransform: 'capitalize', zIndex: 1, position: 'relative' }}>All</Typography>
                            </Box>
                        </Button>
                    </Grid>
                </Grid>
            </Box> */}
        </Stack>
    );
};

export default Home;