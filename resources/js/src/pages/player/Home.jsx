import React, { useState } from 'react';
import Axios from 'axios';
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

import { HStack, VStack } from '../../components/Base';
import { Slider } from '../../components/Part';
import { Clock } from '../../assets/img/feature/svgIcon';

import classNames from 'classnames';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Pagination, Autoplay } from 'swiper';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

import popularIcon from '../../assets/img/feature/fire.svg'

const Home = () => {
    const sportsList = ['Soccer', 'Cricket', 'American Football', 'Tennis', 'Basketball'];
    const [actLiveItem, setActLiveItem] = useState(0);
    const eg = async () => {
        const response = await Axios({
            endpoint: "/sports/get-live",
            method: "POST",
            params: request
        })
    }
    return (
        <Stack>
            <HStack sx={{ py: 2, height: 350 }}>
                <Box sx={{ width: '60%', mr: 2 }}>
                    <Slider />
                </Box>
                <Box sx={{ width: '20%', borderRadius: '20px', bgcolor: '#a115fb', mr: 2, overflow: 'hidden' }}>
                    <Box sx={{ position: 'relative', height: '100%', width: '100%' }}>
                        <Box sx={{ height: '100%' }}>
                            <Box sx={{ height: '100%', width: '100%', top: 0, left: 0, position: 'absolute' }}>
                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-cashback-casino.png" sx={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                            </Box>
                            <VStack sx={{ padding: 3, height: '100%', position: 'relative' }}>
                                <Typography sx={{
                                    width: '100%',
                                    fontSize: '30px',
                                    lineHeight: '106%',
                                    whiteSpace: 'pre-line',
                                    textShadow: '0 3px 5px rgb(9 15 30 / 20%)',
                                    maxWidth: '100%',
                                    letterSpacing: '0.33px',
                                    fontWeight: 800
                                }}>Cashback up to 30% on casinos</Typography>
                                <Link src='casino' sx={{
                                    fontSize: '20px',
                                    padding: '0 25px',
                                    width: '100%',
                                    backgroundColor: '#fff',
                                    boxShadow: '0 10px 35px rgb(0 0 0 / 20%)',
                                    borderRadius: '10px',
                                    fontStyle: 'normal',
                                    fontWeight: 600,
                                    lineHeight: '15px',
                                    marginTop: 'auto',
                                    minHeight: '45px',
                                    color: '#000 !important',
                                    whiteSpace: 'nowrap',
                                    mixBlendMode: 'lighten',
                                    textAlign: 'center',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    textDecoration: "none",
                                    cursor: 'pointer'
                                }}>
                                    Go to casino
                                </Link>
                            </VStack>
                        </Box>
                    </Box>
                </Box>
                <Box sx={{ width: '20%', borderRadius: '20px', bgcolor: '#a115fb', overflow: 'hidden' }}>
                    <Box sx={{ position: 'relative', height: '100%', width: '100%' }}>
                        <Box sx={{ height: '100%' }}>
                            <Box sx={{ height: '100%', width: '100%', top: 0, left: 0, position: 'absolute' }}>
                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" sx={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                            </Box>
                            <VStack sx={{ padding: 3, height: '100%', position: 'relative' }}>
                                <Typography sx={{
                                    width: '100%',
                                    fontSize: '30px',
                                    lineHeight: '106%',
                                    whiteSpace: 'pre-line',
                                    textShadow: '0 3px 5px rgb(9 15 30 / 20%)',
                                    maxWidth: '100%',
                                    letterSpacing: '0.33px',
                                    fontWeight: 800
                                }}>Bonus + 500%</Typography>
                                <Link src='casino' sx={{
                                    fontSize: '20px',
                                    padding: '0 25px',
                                    width: '100%',
                                    backgroundColor: '#fff',
                                    boxShadow: '0 10px 35px rgb(0 0 0 / 20%)',
                                    borderRadius: '10px',
                                    fontStyle: 'normal',
                                    fontWeight: 600,
                                    lineHeight: '15px',
                                    marginTop: 'auto',
                                    minHeight: '45px',
                                    color: '#000 !important',
                                    whiteSpace: 'nowrap',
                                    mixBlendMode: 'lighten',
                                    textAlign: 'center',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    textDecoration: "none",
                                    cursor: 'pointer'
                                }}>
                                    Registration
                                </Link>
                            </VStack>
                        </Box>
                    </Box>
                </Box>
            </HStack>
            <Box>
                <Grid container spacing={2}>
                    <Grid item xs={3} >
                        <Box className='middle-item'>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '24px', fontWeight: '600' }}>Live</Typography>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '13', mt: '3px' }}>Live Games 24/7</Typography>
                            <HStack className='middle-item-img-wrap'>
                                <Box className='middle-item-img'>
                                    <Box component='img' src='frontend/Default/img/svg/tvbet.svg' />
                                </Box>
                            </HStack>
                        </Box>
                    </Grid>
                    <Grid item xs={3} >
                        <Box className='middle-item'>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '24px', fontWeight: '600' }}>Casino</Typography>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '13', mt: '3px' }}>Over 3000 games</Typography>
                            <HStack className='middle-item-img-wrap'>
                                <Box className='middle-item-img'>
                                    <Box component='img' src='frontend/Default/img/svg/casino.svg' />
                                </Box>
                            </HStack>
                        </Box>
                    </Grid>
                    <Grid item xs={3} >
                        <Box className='middle-item'>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '24px', fontWeight: '600' }}>Live Games</Typography>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '13', mt: '3px' }}>Live dealers</Typography>
                            <HStack className='middle-item-img-wrap'>
                                <Box className='middle-item-img'>
                                    <Box component='img' src='frontend/Default/img/svg/live-game.svg' />
                                </Box>
                            </HStack>
                        </Box>
                    </Grid>
                    <Grid item xs={3} >
                        <Box className='middle-item'>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '24px', fontWeight: '600' }}>Poker</Typography>
                            <Typography sx={{ lineHeight: 1, zIndex: 1, fontSize: '13', mt: '3px' }}>Free Tournaments</Typography>
                            <HStack className='middle-item-img-wrap'>
                                <Box className='middle-item-img'>
                                    <Box component='img' src='frontend/Default/img/svg/poker.svg' />
                                </Box>
                            </HStack>
                        </Box>
                    </Grid>
                </Grid>
            </Box>
            <Box sx={{ my: 2 }} >
                <HStack justifyContent='space-between'>
                    <HStack>
                        <Box className='home-tab'>
                            <HStack className='home-tab-title'>
                                <HStack alignItems='center'>
                                    <Box className='populart-icon'>
                                        <Box component='img' src={popularIcon} sx={{ minWidth: '21px', minHeight: '21px' }} />
                                    </Box>
                                    Popular Events
                                </HStack>
                            </HStack>
                        </Box>
                    </HStack>
                </HStack>
                <Stack className='home-tab-content' sx={{ padding: 2, pb: '6px' }}>
                    <Swiper
                        navigation={true}
                        autoplay={true}
                        loop={true}
                        modules={[Navigation, Pagination, Autoplay]}
                        slidesPerView={4}
                        spaceBetween={30}
                        pagination={{
                            clickable: true,
                        }}
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
            <Box>
                <Grid container spacing={2}>
                    <Grid item xs={6}>
                        <HStack justifyContent='space-between'>
                            <HStack>
                                <Box className='home-tab'>
                                    <HStack className='home-tab-title'>
                                        <HStack alignItems='center'>
                                            <Box className='tab-live-bage'>
                                                <Box className='tab-live-bage-center' />
                                            </Box>
                                            Live
                                        </HStack>
                                    </HStack>
                                </Box>
                                <Box className='home-tab-all'>
                                    All
                                </Box>
                            </HStack>
                            <Box sx={{ overflowX: 'auto' }}>
                                <HStack>
                                    {
                                        sportsList.map((item, idx) => (
                                            <Box key={idx} className={classNames("toggle-switcher-item", { 'active': actLiveItem === idx })} onClick={() => setActLiveItem(idx)}>
                                                <Box className="toggle-switcher-icon-wrapper">
                                                    <HStack className="toggle-switcher-icon">
                                                        <i className={classNames("sports-icon", `icon-${item.toLocaleLowerCase().replaceAll(' ', '-')}`, "no-margin")}></i>
                                                    </HStack>
                                                    <Typography component='div' className="toggle-switcher-label">{item}</Typography>
                                                </Box>
                                            </Box>
                                        ))
                                    }
                                </HStack>
                            </Box>
                        </HStack>
                        <Stack className='home-tab-content' sx={{ minHeight: 350 }}>
                            <Box className='match-table-head-underlay' />
                            <Table className='sport-grids'>
                                <TableHead>
                                    <TableRow sx={{ height: '30px' }}>
                                        <TableCell sx={{ pl: '36px', pr: 0 }} align='right'>Time</TableCell>
                                        <TableCell sx={{ pl: '16px' }} colSpan="2">Teams</TableCell>
                                        <TableCell></TableCell>
                                        <TableCell align='center'>1</TableCell>
                                        <TableCell align='center'>X</TableCell>
                                        <TableCell align='center' sx={{ pr: '30px' }}>2</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {
                                        [1, 2, 3, 4].map((item, idx) => (
                                            <TableRow className='match-table-match-row' key={idx}>
                                                <TableCell className='match-table-match-cell'>
                                                    <Box className="match-table-date-info">
                                                        <Box className="match-table-date-time">
                                                            <Box className="match-table-date">18 sep</Box>
                                                            <Box className="match-table-time">21:00</Box>
                                                        </Box>
                                                    </Box>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell' colSpan="2" align='left'>
                                                    <Box className="match-table-primary-info">
                                                        <Box className="match-table-team">Entebbe Archers</Box>
                                                        <Box className="match-table-team">Miracle Eagles</Box>
                                                        <Box className="match-table-score-info">
                                                            <Typography component='span' className="match-table-score">35:44</Typography>
                                                            <Typography component='span' className="match-table-period-score"> (12:20 - 7:14 - 16:10)</Typography>
                                                            <Typography component='span' className="match-table-separator">|</Typography>
                                                            <Typography component='span' className="match-table-match-time">
                                                                <Clock />
                                                                <Typography component='span'>6'</Typography>
                                                            </Typography>
                                                        </Box>
                                                    </Box>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <HStack justifyContent='center' className="match-table-odds-chip-wrap">
                                                        <HStack className="match-table-odds-chip">+1.6</HStack>
                                                    </HStack>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    }
                                </TableBody>
                            </Table>
                        </Stack>
                    </Grid>
                    <Grid item xs={6}>
                        <HStack justifyContent='space-between'>
                            <HStack>
                                <Box className='home-tab'>
                                    <HStack className='home-tab-title'>
                                        <HStack alignItems='center'>
                                            <Box className='populart-icon'>
                                                <Box component='img' src={popularIcon} sx={{ minWidth: '21px', minHeight: '21px' }} />
                                            </Box>
                                            Popular
                                        </HStack>
                                    </HStack>
                                </Box>
                            </HStack>
                            <Box sx={{ overflowX: 'auto' }}>
                                <HStack>
                                    {
                                        sportsList.map((item, idx) => (
                                            <Box key={idx} className={classNames("toggle-switcher-item", { 'active': actLiveItem === idx })} onClick={() => setActLiveItem(idx)}>
                                                <Box className="toggle-switcher-icon-wrapper">
                                                    <HStack className="toggle-switcher-icon">
                                                        <i className={classNames("sports-icon", `icon-${item.toLocaleLowerCase().replaceAll(' ', '-')}`, "no-margin")}></i>
                                                    </HStack>
                                                    <Typography component='div' className="toggle-switcher-label">{item}</Typography>
                                                </Box>
                                            </Box>
                                        ))
                                    }
                                </HStack>
                            </Box>
                        </HStack>
                        <Stack className='home-tab-content' sx={{ minHeight: 350 }}>
                            <Box className='match-table-head-underlay' />
                            <Table className='sport-grids'>
                                <TableHead>
                                    <TableRow sx={{ height: '30px' }}>
                                        <TableCell sx={{ pl: '36px', pr: 0 }} align='right'>Time</TableCell>
                                        <TableCell sx={{ pl: '16px' }} colSpan="2">Teams</TableCell>
                                        <TableCell></TableCell>
                                        <TableCell align='center'>1</TableCell>
                                        <TableCell align='center'>X</TableCell>
                                        <TableCell align='center' sx={{ pr: '30px' }}>2</TableCell>
                                    </TableRow>
                                </TableHead>
                                <TableBody>
                                    {
                                        [1, 2, 3, 4].map((item, idx) => (
                                            <TableRow className='match-table-match-row' key={idx}>
                                                <TableCell className='match-table-match-cell'>
                                                    <Box className="match-table-date-info">
                                                        <Box className="match-table-date-time">
                                                            <Box className="match-table-date">18 sep</Box>
                                                            <Box className="match-table-time">21:00</Box>
                                                        </Box>
                                                    </Box>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell' colSpan="2" align='left'>
                                                    <Box className="match-table-primary-info">
                                                        <Box className="match-table-team">Entebbe Archers</Box>
                                                        <Box className="match-table-team">Miracle Eagles</Box>
                                                        <Box className="match-table-score-info">
                                                            <Typography component='span' className="match-table-score">35:44</Typography>
                                                            <Typography component='span' className="match-table-period-score"> (12:20 - 7:14 - 16:10)</Typography>
                                                            <Typography component='span' className="match-table-separator">|</Typography>
                                                            <Typography component='span' className="match-table-match-time">
                                                                <Clock />
                                                                <Typography component='span'>6'</Typography>
                                                            </Typography>
                                                        </Box>
                                                    </Box>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <HStack justifyContent='center' className="match-table-odds-chip-wrap">
                                                        <HStack className="match-table-odds-chip">+1.6</HStack>
                                                    </HStack>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                                <TableCell className='match-table-match-cell'>
                                                    <Button>1.1</Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    }
                                </TableBody>
                            </Table>
                        </Stack>
                    </Grid>
                    <Grid item xs={6}>
                        <Stack className="section-card">
                            <Box className="section-card-top-line"></Box>
                            <HStack className="section-card-header" sx={{ mb: 3 }}>
                                <HStack alignItems='self-end'>
                                    <Typography variant='h6' sx={{ lineHeight: 1, fontWeight: 700, cursor: 'pointer', letterSpacing: '-.41px' }}>
                                        Casino
                                    </Typography>
                                    <Typography sx={{ ml: 1, opacity: 0.5, lineHeight: 1.2, fontSize: '12px', letterSpacing: '-.29px', fontWeight: 400 }}>8314</Typography>
                                </HStack>
                                <Typography sx={{ fontSize: '11px', letterSpacing: '.07px', fontWeight: 600, color: '#1a68db', cursor: 'pointer' }}>All</Typography>
                            </HStack>
                            <Grid container spacing={2}>
                                {
                                    [1, 2, 3, 4, 5, 6, 7, 8, 9, 1, 2, 3].map((item, idx) => (
                                        <Grid item xs={3} key={idx}>
                                            <Box className="game-card">
                                                <Box className="game-card-image-container">
                                                    <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                                </Box>
                                            </Box>
                                        </Grid>
                                    ))
                                }
                            </Grid>
                        </Stack>
                    </Grid>
                    <Grid item xs={6}>
                        <Stack className="section-card">
                            <Box className="section-card-top-line"></Box>
                            <HStack className="section-card-header" sx={{ mb: 3 }}>
                                <HStack alignItems='self-end'>
                                    <Typography variant='h6' sx={{ lineHeight: 1, fontWeight: 700, cursor: 'pointer', letterSpacing: '-.41px' }}>
                                        LiveGames
                                    </Typography>
                                    <Typography sx={{ ml: 1, opacity: 0.5, lineHeight: 1.2, fontSize: '12px', letterSpacing: '-.29px', fontWeight: 400 }}>8314</Typography>
                                </HStack>
                                <Typography sx={{ fontSize: '11px', letterSpacing: '.07px', fontWeight: 600, color: '#1a68db', cursor: 'pointer' }}>All</Typography>
                            </HStack>
                            <Grid container spacing={2}>
                                {
                                    [1, 2, 3, 4, 5, 6, 7, 8, 9, 1, 2, 3].map((item, idx) => (
                                        <Grid item xs={3} key={idx}>
                                            <Box className="game-card">
                                                <Box className="game-card-image-container">
                                                    <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                                </Box>
                                            </Box>
                                        </Grid>
                                    ))
                                }
                            </Grid>
                        </Stack>
                    </Grid>
                    <Grid item xs={6}>
                        <Stack className="section-card">
                            <Box className="section-card-top-line"></Box>
                            <HStack className="section-card-header" sx={{ mb: 3 }}>
                                <HStack alignItems='self-end'>
                                    <Typography variant='h4' sx={{ lineHeight: 1, fontWeight: 700, cursor: 'pointer', letterSpacing: '-.41px' }}>
                                        Seibet Games
                                    </Typography>
                                </HStack>
                                <Typography sx={{ fontSize: '11px', letterSpacing: '.07px', fontWeight: 600, color: '#1a68db', cursor: 'pointer' }}>All</Typography>
                            </HStack>
                            <Grid container spacing={2}>
                                {
                                    [1, 2, 3, 4].map((item, idx) => (
                                        <Grid item xs={3} key={idx}>
                                            <Box className="game-card">
                                                <Box className="game-card-image-container">
                                                    <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                                </Box>
                                            </Box>
                                        </Grid>
                                    ))
                                }
                            </Grid>
                        </Stack>
                    </Grid>
                    <Grid item xs={6}>
                        <Stack className="section-card" sx={{ height: '100%', padding: 0 }}>
                            <Box className="section-card-top-line"></Box>
                            <Box className="section-card-bg">
                                <HStack className="SectionPokerBanner-bg" justifyContent='flex-end'>
                                    <Box className="WithSelfBlurGlow">
                                        <Box className="WithSelfBlurGlow-glow">
                                            <Box sx={{ width: 'auto', height: '100%', pointerEvents: 'none' }}>
                                                <Box component='img' src='frontend/Default/img/_src/home-poker-banner-bg.webp' sx={{ height: '100%', width: '100%' }} />
                                            </Box>
                                        </Box>
                                        <Box className="WithSelfBlurGlow-content-wrapper">
                                            <Box sx={{ width: 'auto', height: '100%', pointerEvents: 'none' }}>
                                                <Box component='img' src='frontend/Default/img/_src/home-poker-banner-bg.webp' sx={{ height: '100%', maxWidth: '100%', objectFit: 'contain', opacity: 1 }} />
                                            </Box>
                                        </Box>
                                    </Box>
                                </HStack>
                            </Box>
                            <HStack className="section-card-header" sx={{ mb: 3, padding: '25px' }}>
                                <HStack alignItems='self-end'>
                                    <Typography variant='h4' sx={{ lineHeight: 1, fontWeight: 700, cursor: 'pointer', letterSpacing: '-.41px' }}>
                                        Poker
                                    </Typography>
                                </HStack>
                            </HStack>
                            <Box sx={{ flexGrow: 1, padding: '25px' }}>
                                <Stack sx={{ height: '100%', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                                    <Typography sx={{ fontSize: '16px', lineHeight: '24px', fontWeight: 400 }}>Come in, play and win</Typography>
                                    <Button className="SectionPokerBanner_contentButton">Get bonus</Button>
                                </Stack>
                            </Box>
                        </Stack>
                    </Grid>
                </Grid>
            </Box>
        </Stack>
    );
};

export default Home;