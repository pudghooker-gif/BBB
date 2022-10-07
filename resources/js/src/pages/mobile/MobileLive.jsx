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
import Collapse from '@mui/material/Collapse';
import TableHead from '@mui/material/TableHead';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';

import AccessTimeIcon from '@mui/icons-material/AccessTime';
import SearchIcon from '@mui/icons-material/Search';
import KeyboardDoubleArrowUpIcon from '@mui/icons-material/KeyboardDoubleArrowUp';
import KeyboardDoubleArrowDownIcon from '@mui/icons-material/KeyboardDoubleArrowDown';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';

import { Clock } from '../../assets/img/feature/svgIcon';
import { HStack, VStack } from '../../components/Base';

const LiveEvent = () => {
    const [collapse, setCollapse] = useState(false);
    return (
        <Box sx={{ bgcolor: '#fff', borderRadius: 3, mb: 1 }}>
            <HStack onClick={() => setCollapse(!collapse)} justifyContent='space-between' alignItems='center' sx={{ py: 1.25, px: 2 }}>
                <Stack>
                    <Typography sx={{ fontSize: 12, lineHeight: 1.2, color: '#94a6cd' }}>Germany</Typography>
                    <Typography sx={{ fontSize: 14, lineHeight: 1.3, fontWeight: 600, color: '#090f1e' }}> 2nd Bundesliga</Typography>
                </Stack>
                {
                    collapse ? <KeyboardArrowUpIcon sx={{ color: '#090f1e' }} /> : <KeyboardArrowDownIcon sx={{ color: '#090f1e' }} />
                }
            </HStack>
            <Collapse in={collapse} timeout="auto" unmountOnExit>
                {
                    [0, 1, 2, 3].map((item) => (
                        <Box key={item} className='popular-wrap' sx={{ py: 1.25, px: 2, borderTop: '1px solid #607bb61a' }}>
                            <Stack className="live-event-main" sx={{ padding: '0px !important' }}>
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
                    ))
                }
            </Collapse>
        </Box>
    )
}

const LiveMatch = ({ sport }) => {
    const [collapse, setCollapse] = useState(false);

    return (
        <Box>
            <Button
                sx={{ '& .MuiButton-endIcon': { mr: 0, ml: 'auto' } }}
                startIcon={<i className={classNames("sports-icon", `icon-${sport.toLocaleLowerCase().replaceAll(' ', '-')}`)}></i>}
                endIcon={collapse ? <KeyboardArrowUpIcon /> : <KeyboardArrowDownIcon />}
                className='sports-list-item-btn'
                onClick={() => setCollapse(!collapse)}
            >
                <Typography component='span' sx={{ fontSize: 14, fontWeight: 500 }}>
                    {sport}
                </Typography>
            </Button>
            <Collapse in={collapse} timeout="auto" unmountOnExit>
                {
                    [0, 1, 2, 3].map((item) => (
                        <LiveEvent key={item} />
                    ))
                }
            </Collapse>
        </Box>
    )
}

const SportItem = ({ sport }) => {
    return (
        <Button
            sx={{ '& .MuiButton-endIcon': { mr: 0, ml: 'auto' } }}
            startIcon={<i className={classNames("sports-icon", `icon-${sport.toLocaleLowerCase().replaceAll(' ', '-')}`)}></i>}
            endIcon={<KeyboardArrowRightIcon />}
            className='sports-list-item-btn'
            // onClick={() => setCollapse(!collapse)}
        >
            <Typography component='span' sx={{ fontSize: 15, fontWeight: 600 }}>
                {sport}
            </Typography>
            <Typography component='span' sx={{ fontSize: 14, color: '#6c7da3', ml: 1 }}>
                32
            </Typography>
        </Button>
    )
}

const EventPart = ({ sportsList }) => {
    return (
        <>
            <HStack className='sports-live-over' sx={{ mx: -2, px: 2 }}>
                {
                    sportsList.map((item, idx) => (
                        <Stack className='live-sports-item-m' key={idx}>
                            <IconButton sx={{ borderRadius: 4, }}>
                                <i className={classNames("sports-icon", `icon-${item.toLocaleLowerCase().replaceAll(' ', '-')}`)} />
                            </IconButton>
                            <Typography>{item}</Typography>
                        </Stack>
                    ))
                }
            </HStack>
            <Stack className='mobile-live-btns'>
                {
                    sportsList.map((item, idx) => (
                        <LiveMatch key={idx} sport={item} />
                    ))
                }
            </Stack>
        </>
    )
}

const SportPart = ({ sportsList }) => {
    return (
        <Box>
            <Stack className='mobile-live-btns'>
                {
                    sportsList.map((item, idx) => (
                        <SportItem key={idx} sport={item} />
                    ))
                }
            </Stack>
        </Box>
    )
}

const Live = () => {
    const list = [{ name: 'Home', route: '/home' }, { name: 'Live', route: '/sports/live' }, { name: 'Sports', route: '/sports/prematch' }, { name: 'World Cup 22', route: '/sports/prematch' }, { name: 'Casino', route: '/casino/all' }, { name: 'Live-Casino', route: '/live-casino' }, { name: 'Poker', route: '/poker' }];
    const sportsList = ['Soccer', 'Basketball', 'Cricket', 'Table Tennis', 'American Football', 'Tennis'];

    const [active, setActive] = useState(0);
    const [esIt, setEsIt] = useState(0);

    const go = (idx) => {
        setActive(idx);
        navigate(list[idx].route);
    }

    return (
        <Stack>
            <Box sx={{ borderBottom: '1px solid #141b2e', mx: -2, px: 2 }}>
                <HStack sx={{ width: 'calc(100% + 16px)', mx: -2 }}>
                    <HStack sx={{ height: 45, overflow: 'auto', alignItems: 'center', py: 1, pl: 2 }} className='bodyTab'>
                        {
                            list.map((item, idx) => (
                                <Stack
                                    key={idx}
                                    className={classNames({ 'able': idx === active })}
                                    sx={{
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        borderRadius: 50,
                                        color: '#fff',
                                        fontSize: 14,
                                        fontWeight: 600,
                                        height: '24px',
                                        lineHeight: 1,
                                        py: 0,
                                        px: 1.25,
                                        whiteSpace: 'nowrap'
                                    }}
                                    onClick={() => go(idx)}
                                >
                                    {item.name}
                                </Stack>
                            ))
                        }
                    </HStack>
                    <IconButton>
                        <SearchIcon />
                    </IconButton>
                </HStack>
            </Box>
            <HStack sx={{ my: 2 }}>
                <HStack className='switch-btn'>
                    <Button className={classNames('btn', { 'active': esIt === 0 })} onClick={() => setEsIt(0)}>Events</Button>
                    <Button className={classNames('btn', { 'active': esIt === 1 })} onClick={() => setEsIt(1)}>Sports</Button>
                </HStack>
                <IconButton disabled={esIt > 0} className='able' sx={{ borderRadius: 3, ml: 1, padding: .75 }}>
                    <KeyboardDoubleArrowDownIcon />
                </IconButton>
            </HStack>
            {
                esIt ? <SportPart {...{ sportsList }} /> : <EventPart {...{ sportsList }} />
            }
        </Stack>
    )
};

export default Live;