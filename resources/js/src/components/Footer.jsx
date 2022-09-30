import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Link from '@mui/material/Link';
import Menu from '@mui/material/Menu';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';


import MarkChatUnreadIcon from '@mui/icons-material/MarkChatUnread';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import HelpIcon from '@mui/icons-material/Help';
import PublishIcon from '@mui/icons-material/Publish';
import TelegramIcon from '@mui/icons-material/Telegram';
import InstagramIcon from '@mui/icons-material/Instagram';
import FacebookIcon from '@mui/icons-material/Facebook';
import TwitterIcon from '@mui/icons-material/Twitter';

import { HStack } from './Base';
import {
    UEFA, UFC, WTA, FIBA, NNL, ATP, ITF, FIFA,
    Visa, Master, GooglePay, ApplePay, BitCoin, Qiwi, Ether, Tether, Skrill, Paytm, Payneer, Cos, FK, WebMoney, MuchBetter, JCB, Discover, Interace, AstroPay,
} from "../assets/img/feature/svgIcon";

import logo from '../assets/img/logo/logo.png';
import win10 from '../assets/img/feature/win10.svg';
import ios from '../assets/img/feature/ios.svg';
import android from '../assets/img/feature/android.svg';
import enlang from '../assets/img/feature/en.svg';
import ptlang from '../assets/img/feature/pt.svg';
import casinoMentor from '../assets/img/feature/casino-mentor.png';
import miglioriCasinoOnline from '../assets/img/feature/migliori-casino-online.png';
import bestBitcoinCasino from '../assets/img/feature/best-bitcoin-casino.png';
import casinosAnalyzer from '../assets/img/feature/casinos-analyzer.png';
import cricketBettingWali from '../assets/img/feature/cricket-betting-wali.png';
import br from '../assets/img/feature/br.svg';
import verifiedSeibet from '../assets/img/feature/verified-seibet.png';

const Footer = () => {
    const [langList, setLangList] = useState(null);

    const showLang = (event) => {
        setLangList(event.currentTarget);
    };

    const closeLang = () => {
        setLangList(null);
    };
    return (
        <Box className='footer' sx={{ py: 4 }}>
            <HStack sx={{ mb: 6, alignItems: 'center' }}>
                <Box component='img' src={logo} sx={{ height: 20 }} />
                <Box sx={{ ml: 3 }} className='footer-line' />
            </HStack>
            <HStack>
                <HStack sx={{ maxWidth: '846px' }}>
                    <Stack sx={{ width: 140 }}>
                        <Typography sx={{ fontSize: 12, lineHeight: '14px', fontWeight: 600 }}>Support 24/7</Typography>
                        <Typography sx={{ fontSize: 10, lineHeight: 1, color: '#34405e', my: .5 }}>Write to us if You still have any questions!</Typography>
                        <HStack sx={{ mt: 2 }} alignItems="center">
                            <IconButton className="contact-btn">
                                <MarkChatUnreadIcon sx={{ fontSize: '14px' }} />
                            </IconButton>
                            <Link href="" sx={{ textDecoration: "none", color: '#fff', fontSize: 12, ml: 1.5 }}>contact@seibet.com</Link>
                        </HStack>
                    </Stack>
                    <HStack sx={{ ml: 9 }}>
                        <Stack>
                            <Typography sx={{ fontSize: 12, lineHeight: 1, color: '#34405e', mb: 2, textTransform: 'uppercase' }}>Information</Typography>
                            <Typography sx={{ fontSize: 12, mt: 1.25 }}>Rules</Typography>
                            <Typography sx={{ fontSize: 12, mt: 1.25 }}>Bonuses and promotions</Typography>
                        </Stack>
                        <Stack sx={{ ml: 9 }}>
                            <Typography sx={{ fontSize: 12, lineHeight: 1, color: '#34405e', mb: 2, textTransform: 'uppercase' }}>Categories</Typography>
                            <HStack >
                                <Stack>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Live</Typography>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Sports</Typography>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Promotions</Typography>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Live-Games</Typography>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Poker</Typography>
                                </Stack>
                                <Stack sx={{ ml: 9 }}>
                                    <Typography sx={{ fontSize: 12, mt: 1.25 }}>Casino</Typography>
                                </Stack>
                            </HStack>
                        </Stack>
                    </HStack>
                </HStack>
                <HStack sx={{ mt: 'auto', ml: 'auto' }}>
                    <Stack sx={{ mr: 1.25, justifyContent: 'space-between' }}>
                        <Button className='app-download' sx={{ display: 'flex' }}>
                            <HStack alignItems='center'>
                                <Box component='img' src={ios} sx={{ height: '25px' }} />
                                <Stack sx={{ ml: 1, textTransform: 'capitalize', textAlign: 'start', color: 'white' }}>
                                    <Typography sx={{ fontSize: 8, lineHeight: '10px', color: 'hsla(0,0%,100%,.5)' }}>Application</Typography>
                                    <Typography sx={{ fontWeight: 600, fontSize: 10 }}>for IOS</Typography>
                                </Stack>
                            </HStack>
                            <HelpIcon sx={{ height: 16, opacity: .45 }} />
                        </Button>
                        <Button className='app-download' sx={{ display: 'flex' }}>
                            <HStack alignItems='center'>
                                <Box component='img' src={android} sx={{ height: '25px' }} />
                                <Stack sx={{ ml: 1, textTransform: 'capitalize', textAlign: 'start', color: 'white' }}>
                                    <Typography sx={{ fontSize: 8, lineHeight: '10px', color: 'hsla(0,0%,100%,.5)' }}>Application</Typography>
                                    <Typography sx={{ fontWeight: 600, fontSize: 10 }}>for Android</Typography>
                                </Stack>
                            </HStack>
                            <HelpIcon sx={{ height: 16, opacity: .45 }} />
                        </Button>
                    </Stack>
                    <Button sx={{ display: 'flex', flexDirection: 'column', padding: 1.25, border: '1px solid hsla(0,0%,100%,.15)', height: '94px', width: '94px', borderRadius: '12px', justifyContent: 'space-between', cursor: 'pointer' }}>
                        <HStack alignItems='center' justifyContent='space-between' sx={{ width: '100%' }}>
                            <Box component='img' src={win10} sx={{ height: '25px' }} />
                            <ChevronRightIcon sx={{ height: 16, opacity: .45 }} />
                        </HStack>
                        <Stack sx={{ textTransform: 'capitalize', textAlign: 'start', color: 'white' }}>
                            <Typography sx={{ fontSize: 8, lineHeight: '10px', color: 'hsla(0,0%,100%,.5)' }}>Application</Typography>
                            <Typography sx={{ fontWeight: 600, fontSize: 10 }}>for Windows</Typography>
                        </Stack>
                    </Button>
                </HStack>
            </HStack>
            <Box sx={{ mt: 5, mb: 3 }} className='footer-line' />
            <HStack alignItems='center'>
                <HStack alignItems='center'>
                    <IconButton className='social_link' sx={{ mr: 1.25 }}>
                        <Link src="#">
                            <TelegramIcon />
                        </Link>
                    </IconButton>
                    <IconButton className='social_link' sx={{ mr: 1.25 }}>
                        <Link src="#">
                            <InstagramIcon />
                        </Link>
                    </IconButton>
                    <IconButton className='social_link' sx={{ mr: 1.25 }}>
                        <Link src="#">
                            <FacebookIcon />
                        </Link>
                    </IconButton>
                    <IconButton className='social_link'>
                        <Link src="#">
                            <TwitterIcon />
                        </Link>
                    </IconButton>
                </HStack>
                <HStack className='payments'>
                    <UEFA />
                    <UFC />
                    <WTA />
                    <FIBA />
                    <NNL />
                    <ATP />
                    <ITF />
                    <FIFA />
                </HStack>
                <HStack>
                    <Box sx={{ flexGrow: 0 }}>
                        <Button
                            onClick={showLang}
                            className='footer-btn'
                        >
                            <Typography sx={{ fontSize: 12, color: '#fff', fontWeight: 700 }}>
                                EN
                            </Typography>
                            <Box component='img' sx={{ ml: 1.25, width: 22 }} src={enlang} />
                        </Button>
                        <Menu
                            sx={{
                                mb: (theme) => theme.spacing(6),
                                [`& .MuiPopover-paper`]: {
                                    bgcolor: 'white',
                                    borderRadius: 2,
                                },
                                [`& .MuiPopover-paper ul`]: {
                                    minWidth: (theme) => theme.spacing(8)
                                }
                            }}
                            id="menu-appbar"
                            anchorEl={langList}
                            anchorOrigin={{
                                vertical: 'top',
                                horizontal: 'right',
                            }}
                            keepMounted
                            transformOrigin={{
                                vertical: 'bottom',
                                horizontal: 'right',
                            }}
                            open={Boolean(langList)}
                            onClose={closeLang}
                        >
                            <MenuItem onClick={closeLang}>
                                <Typography textAlign="center" sx={{ color: 'black', fontSize: (theme) => theme.spacing(1.5) }}>EN</Typography>
                                <Box component='img' src={enlang} sx={{
                                    width: '15px',
                                    height: '15px',
                                    borderRadius: 50,
                                    ml: 1
                                }} />
                            </MenuItem>
                            <MenuItem onClick={closeLang}>
                                <Typography textAlign="center" sx={{ color: 'black', fontSize: (theme) => theme.spacing(1.5) }}>PT</Typography>
                                <Box component='img' src={ptlang} sx={{
                                    width: '15px',
                                    height: '15px',
                                    borderRadius: 50,
                                    ml: 1
                                }} />
                            </MenuItem>
                        </Menu>
                    </Box>
                    <IconButton className='footer-btn' sx={{ ml: 1.25 }}>
                        <PublishIcon />
                    </IconButton>
                </HStack>

            </HStack>
            <Box sx={{ my: 3 }} className='footer-line-full' />
            <HStack alignItems='center' justifyContent='space-between'>
                <Visa />
                <Master />
                <GooglePay />
                <ApplePay />
                <BitCoin />
                <Qiwi />
                <Ether />
                <Tether />
                <Skrill />
                <Paytm />
                <Payneer />
                <Cos />
                <FK />
                <WebMoney />
                <MuchBetter />
                <JCB />
                <Discover />
                <Interace />
                <AstroPay />
            </HStack>
            <Box sx={{ my: 3 }} className='footer-line-full' />
            <HStack>
                <Box>
                    <Typography component='span' sx={{ fontSize: '9px', fontWeight: 400, color: '#34405e', flex: 'auto' }}>© 2022 Seibet. </Typography>
                    <Typography component='span' sx={{ fontSize: '9px', fontWeight: 400, color: '#34405e', flex: 'auto' }}>1win.pro operated by 1WIN N.V. which is registered at Dr. H. Fergusonweg 1, Curaçao, with company number 147039, and having gaming license 8048/JAZ2018-040 and all rights to operate the gaming software. MFI INVESTMENTS LIMITED, a company, whose registered office is at 3, Chytron Street, Flat/Office 301, P.C. 1075 Nicosia, Cyprus with company number HE386738.EU company MFI Investments Ltd is providing payment services as an agent according to the license agreement concluded between MFI INVESTMENTS LIMITED and 1WIN N.V.</Typography>
                </Box>
                <HStack sx={{ ml: 9 }}>
                    <HStack alignItems='center'>
                        <HStack>
                            <Link>
                                <Box component='img' src={casinoMentor} sx={{ height: '25px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack>
                            <Link>
                                <Box component='img' src={miglioriCasinoOnline} sx={{ height: '25px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack>
                            <Link>
                                <Box component='img' src={bestBitcoinCasino} sx={{ height: '25px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack>
                            <Link>
                                <Box component='img' src={casinosAnalyzer} sx={{ height: '25px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack>
                            <Link>
                                <Box component='img' src={cricketBettingWali} sx={{ height: '25px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack alignItems='center'>
                            <Link>
                                <Box component='img' src={br} sx={{ height: '39px' }} />
                            </Link>
                            <Box className='split' />
                        </HStack>
                        <HStack alignItems='center' sx={{ textDecoration: "none", color: '#34405e', fontSize: 16, fontWeight: 800 }}>
                            <Link>
                                <Box component='img' src={verifiedSeibet} sx={{ height: '39px' }} />
                            </Link>
                            18+
                        </HStack>
                    </HStack>
                </HStack>
            </HStack>
        </Box>
    );
};

export default Footer;