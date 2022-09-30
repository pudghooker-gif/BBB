import * as React from 'react';
import { useNavigate } from 'react-router-dom';

import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import InputAdornment from '@mui/material/InputAdornment';
import FormControl from '@mui/material/FormControl';
import OutlinedInput from '@mui/material/OutlinedInput';
import IconButton from '@mui/material/IconButton';

import PersonOutlineIcon from '@mui/icons-material/PersonOutline';
import LockOutlinedIcon from '@mui/icons-material/LockOutlined';
import Visibility from '@mui/icons-material/Visibility';
import VisibilityOff from '@mui/icons-material/VisibilityOff';
import LoginIcon from '@mui/icons-material/Login';

import { VStack } from 'components/Base';

interface State {
    user: string;
    password: string;
    showPassword: boolean;
}

const Login = () => {
    const navigate = useNavigate();
    const [values, setValues] = React.useState<State>({
        user: '',
        password: '',
        showPassword: false,
    });

    const handleChange =
        (prop: keyof State) => (event: React.ChangeEvent<HTMLInputElement>) => {
            setValues({ ...values, [prop]: event.target.value });
        };

    const handleClickShowPassword = () => {
        setValues({
            ...values,
            showPassword: !values.showPassword,
        });
    };

    const handleMouseDownPassword = (event: React.MouseEvent<HTMLButtonElement>) => {
        event.preventDefault();
    };

    return (
        <VStack sx={{ height: '100vh' }}>
            <Box
                component="img"
                src={require('assets/img/logo/logo.png')}
                alt='logo'
                sx={{ maxHeight: (theme) => theme.spacing(30) }}
            />


            <FormControl sx={{ m: 1.5, width: (theme) => theme.spacing(45) }} variant="outlined">
                <OutlinedInput
                    value={values.user}
                    placeholder="User"
                    onChange={handleChange('user')}
                    startAdornment={<InputAdornment position="start">
                        <PersonOutlineIcon color='error' />
                    </InputAdornment>}
                    sx={{
                        [`fieldset`]: {
                            display: 'none'
                        },
                        bgcolor: '#21242ac9',
                        borderRadius: 0,
                        py: 1
                    }}
                />
            </FormControl>
            <FormControl sx={{ m: 1.5, width: (theme) => theme.spacing(45) }} variant="outlined">
                <OutlinedInput
                    type={values.showPassword ? 'text' : 'password'}
                    value={values.password}
                    onChange={handleChange('password')}
                    placeholder="Password"
                    startAdornment={<InputAdornment position="start">
                        <LockOutlinedIcon color='error' />
                    </InputAdornment>}
                    endAdornment={
                        <InputAdornment position="end">
                            <IconButton
                                onClick={handleClickShowPassword}
                                onMouseDown={handleMouseDownPassword}
                                edge="end"
                            >
                                {values.showPassword ? <VisibilityOff /> : <Visibility />}
                            </IconButton>
                        </InputAdornment>
                    }
                    sx={{
                        [`fieldset`]: {
                            display: 'none'
                        },
                        bgcolor: '#21242ac9',
                        borderRadius: 0,
                        py: 1
                    }}
                />
            </FormControl>
            <Button
                variant="contained"
                color="error"
                sx={{
                    m: 1.5,
                    width: (theme) => theme.spacing(30),
                    py: 1.5,
                    px: 3,
                    borderRadius: 0,
                    display: 'flex',
                    justifyContent: 'space-between'
                }}
                onClick={() => navigate('/sports/home')}
            >
                <Typography variant='h6'>
                    Sign IN
                </Typography>
                <LoginIcon fontSize="large" />
            </Button>
        </VStack>
    );
}

export default Login;