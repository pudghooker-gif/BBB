import { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import Axios from '../providers/request';

import Box from '@mui/material/Box';
import Grow from '@mui/material/Grow';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Collapse from '@mui/material/Collapse';
import TableRow from '@mui/material/TableRow';
import TableHead from '@mui/material/TableHead';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import Typography from '@mui/material/Typography';

import SportsSoccerIcon from '@mui/icons-material/SportsSoccer';
import StarBorderIcon from '@mui/icons-material/StarBorder';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';
import ListAltIcon from '@mui/icons-material/ListAlt';
import SearchIcon from '@mui/icons-material/Search';
import StarIcon from '@mui/icons-material/Star';
import KeyboardDoubleArrowDownIcon from '@mui/icons-material/KeyboardDoubleArrowDown';

import classNames from 'classnames';
import { Clock, Loader } from '../assets/img/feature/svgIcon';
import { HStack } from './Base';

import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Pagination, Autoplay } from 'swiper';

import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

export const Loading = () => {
  return (
    <HStack justifyContent='center' sx={{ p: 1 }}>
      <Loader />
    </HStack>
  )
}

export const Slider = () => {
  return (
    <Swiper
      navigation={true}
      pagination={{
        clickable: true,
      }}
      autoplay={{ delay: 5000 }}
      loop={true}
      modules={[Navigation, Pagination, Autoplay]}
      className='bet-swiper'
    >
      <SwiperSlide><Box component='img' alt='slider' src='https://1win.pro/img/PWA_USD_en.43fbf7fc-1600.webp' /></SwiperSlide>
      <SwiperSlide><Box component='img' alt='slider' src='https://1win.pro/img/bonus_hover_1.f76a358c-1600.webp' /></SwiperSlide>
      <SwiperSlide><Box component='img' alt='slider' src='https://cdn-1win.xyz/banner-files/46gFMSTQIPqJxLalK5SGf1Qu3vBY1sRPesH8oR3qqpg9WVTmHGsLr4EVG50m6vA-Yhk3QAH7z8q80aD30ApLYjvPhvJBl8FvX1ER.png' /></SwiperSlide>
      <SwiperSlide><Box component='img' alt='slider' src='https://1win.pro/img/1winpoker_en-min.fc17484b-1600.webp' /></SwiperSlide>
    </Swiper>
  );
}

export const Event = () => {
  const [active, setActive] = useState(true);
  return (
    <Stack sx={{ overflow: 'hidden' }}>
      <Box>
        <Stack className='event-content'>
          <HStack justifyContent='space-between' sx={{ mb: 2 }}>
            <Box sx={{ pt: 2.5, pl: 2.5 }}>
              <HStack sx={{ mb: 1 }} alignItems='center'>
                <Typography varient='h2' sx={{ mr: 2, fontSize: 30, fontWeight: 900 }}>0 : 0</Typography>
                <Typography sx={{ margin: 'auto', mr: 2, py: .75, px: 2, borderRadius: 4, bgcolor: '#97aee11c' }}>2 half 47 '</Typography>
                <IconButton>
                  <StarIcon />
                </IconButton>
              </HStack>
              <Typography sx={{ fontSize: 12, color: '#ffffff80' }}>07 Sep, Wed</Typography>
              <Stack sx={{ my: 2 }}>
                <Typography sx={{ fontSize: 15 }}>Home Team</Typography>
                <Typography sx={{ fontSize: 15 }}>Away Team</Typography>
              </Stack>
              <Stack>
                <HStack>
                  <Typography sx={{ fontSize: 12, color: '#ffffff80', mr: 1 }}>Soccer</Typography>
                  <i className={classNames("sports-icon", `icon-soccer`)}></i>
                </HStack>
                <HStack>
                  <Typography sx={{ fontSize: 12, color: '#ffffff80' }}>England Reserve Matches</Typography>
                </HStack>
              </Stack>
            </Box>
          </HStack>
          <Stack sx={{ bgcolor: '#141b2e', borderRadius: 2 }}>
            <HStack justifyContent='space-between' alignItems='center' sx={{ padding: 2 }}>
              <Button className='btn'>All</Button>
              <IconButton className='btn'>
                <KeyboardDoubleArrowDownIcon />
              </IconButton>
            </HStack>
            <Box sx={{ padding: 2 }}>
              <Grid container spacing={2}>
                {
                  [1, 2, 3, 4, 5, 6, 7, 8, 9].map((ite, idx) => (
                    <Grid item xs={6} key={idx}>
                      <Box sx={{ borderRadius: 2, overflow: 'hidden' }}>
                        <HStack className='market-head'>
                          <Typography sx={{ fontSize: '12px' }}>
                            1X2
                          </Typography>
                          <IconButton onClick={() => setActive(!active)}>
                            {
                              active ? <KeyboardArrowUpIcon /> : <KeyboardArrowDownIcon />
                            }
                          </IconButton>
                        </HStack>
                        <Collapse in={active} timeout="auto" unmountOnExit>
                          <Box className='market-body'>
                            <Box className='market-wrap'>
                              <Button className='market-btn'>
                                <Typography className='market-name'>
                                  1X2
                                </Typography>
                                <Typography className='market-odd'>
                                  2.57
                                </Typography>
                              </Button>
                            </Box>
                            <Box className='market-wrap'>
                              <Button className='market-btn'>
                                <Typography className='market-name'>
                                  1X2
                                </Typography>
                                <Typography className='market-odd'>
                                  2.57
                                </Typography>
                              </Button>
                            </Box>
                            <Box className='market-wrap'>
                              <Button className='market-btn'>
                                <Typography className='market-name'>
                                  1X2
                                </Typography>
                                <Typography className='market-odd'>
                                  2.57
                                </Typography>
                              </Button>
                            </Box>
                            <Box className='market-wrap'>
                              <Button className='market-btn'>
                                <Typography className='market-name'>
                                  1X2
                                </Typography>
                                <Typography className='market-odd'>
                                  2.57
                                </Typography>
                              </Button>
                            </Box>
                            <Box className='market-wrap'>
                              <Button className='market-btn'>
                                <Typography className='market-name'>
                                  1X2
                                </Typography>
                                <Typography className='market-odd'>
                                  2.57
                                </Typography>
                              </Button>
                            </Box>
                          </Box>
                        </Collapse>
                      </Box>
                    </Grid>
                  ))
                }
              </Grid>
            </Box>
          </Stack>
        </Stack>
      </Box>
    </Stack>
  )
}