import { Typography } from '@mui/material';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import InputAdornment from '@mui/material/InputAdornment';
import { HStack } from '../../components/Base';
import { Slider } from '../../components/Part';

import StarIcon from '@mui/icons-material/Star';
import SearchIcon from '@mui/icons-material/Search';

import classNames from 'classnames';

const Casino = () => {
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
                                            startIcon={<i className={classNames("sports-icon", `icon-soccer`, "no-margin")}></i>}
                                            endIcon={<Typography component='span' sx={{ fontSize: '12px !important' }}>10</Typography>}
                                            className='sports-list-item-btn'
                                        >
                                            <Typography component='span' sx={{ fontSize: '14px' }}>
                                                All Games
                                            </Typography>
                                        </Button>
                                    </Box>
                                </Box>
                            </Box>
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
                                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                            </Box>
                                        </Box>
                                        <Box className="game-card" sx={{ width: '25%' }}>
                                            <Box className="game-card-image-container">
                                                <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
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
                                    Casino Game Provider
                                </Typography>
                            </HStack>
                            <Grid container spacing={2}>
                                {
                                    [1, 2, 3, 4, 5, 6, 7, 8, 9, 1, 2, 3, 1, 2, 3, 4, 5, 6, 6].map((item, idx) => (
                                        <Grid item xs={2} key={idx}>
                                            <Box className="game-card">
                                                <Box className="game-card-image-container">
                                                    <Box component='img' src="frontend/Default/img/_src/bonus-banner-deposit.avif" className="game-card-image" />
                                                </Box>
                                            </Box>
                                        </Grid>
                                    ))
                                }
                            </Grid>
                        </Box>
                    </Box>
                </Grid>
            </Grid>
        </Box >
    )
};

export default Casino;