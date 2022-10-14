import { styled } from '@mui/material/styles';

import Box from '@mui/material/Box';
import Radio from '@mui/material/Radio';
import Stack from '@mui/material/Stack';
import Badge from '@mui/material/Badge';
import TableCell from '@mui/material/TableCell';
import OutlinedInput from '@mui/material/OutlinedInput';

export const StyledBadge = styled(Badge)(({ theme }) => ({
  '& .MuiBadge-badge': {
    backgroundColor: '#44b700',
    color: '#44b700',
    boxShadow: `0 0 0 2px ${theme.palette.background.paper}`,
    '&::after': {
      position: 'absolute',
      top: 0,
      left: 0,
      width: '100%',
      height: '100%',
      borderRadius: '50%',
      animation: 'ripple 1.2s infinite ease-in-out',
      border: '1px solid currentColor',
      content: '""',
    },
  },
  '@keyframes ripple': {
    '0%': {
      transform: 'scale(.8)',
      opacity: 1,
    },
    '100%': {
      transform: 'scale(2.4)',
      opacity: 0,
    },
  },
}));

export const BoxBorder = styled(Box)(({ theme }) => ({
  // borderWidth: theme.spacing(0.4),
  borderStyle: 'solid',
  borderColor: theme.palette.secondary.main
}))

export const HStack = styled(Stack)(({ theme }) => ({
  flexDirection: 'row',
}))

export const VStack = styled(Stack)(({ theme }) => ({
  flexDirection: 'column',
}))

export const LayoutWrap = styled(Box)(({ theme }) => ({
  minHeight: 'calc(100vh - 68px - 48px)',
  padding: theme.spacing(4, 0),
  // backgroundImage: `url(${require('../assets/img/background/back.jpg')})`,
  backgroundAttachment: 'fixed',
  backgroundPosition: 'right',
  backgroundRepeat: 'no-repeat',
  backgroundSize: 'cover'
}))

export const FullLayoutWrap = styled(Box)(({ theme }) => ({
  minHeight: '100vh',
  // backgroundImage: `url(${require('../assets/img/background/login.png')})`,
  backgroundRepeat: 'no-repeat',
  backgroundSize: 'cover',
  opacity: 0.6
}))

export const OutInput = styled(OutlinedInput)(({ theme }) => ({
  width: theme.spacing(20),
  [`& .MuiSelect-select`]: {
    backgroundColor: theme.palette.background.paper,
    paddingTop: theme.spacing(0.75),
    paddingBottom: theme.spacing(0.75),
  },
  [`fieldset`]: {
    borderColor: `${theme.palette.secondary.main} !important`,
    borderRadius: 0,
    borderWidth: theme.spacing(0.25),
    borderStyle: 'solid',
  }
}));

export const TblCell = styled(TableCell)(({ theme }) => ({
  padding: theme.spacing(0.5),
  overflow: 'hidden',
  // height: theme.spacing(0.5),
  borderRight: '1px solid rgba(81, 81, 81, 1)'
}))

const BpIcon = styled('span')(({ theme }) => ({
  borderRadius: '50%',
  width: 16,
  height: 16,
  boxShadow:
    theme.palette.mode === 'dark'
      ? '0 0 0 1px rgb(16 22 26 / 40%)'
      : 'inset 0 0 0 1px rgba(16,22,26,.2), inset 0 -1px 0 rgba(16,22,26,.1)',
  backgroundColor: theme.palette.mode === 'dark' ? '#394b59' : '#f5f8fa',
  backgroundImage:
    theme.palette.mode === 'dark'
      ? 'linear-gradient(180deg,hsla(0,0%,100%,.05),hsla(0,0%,100%,0))'
      : 'linear-gradient(180deg,hsla(0,0%,100%,.8),hsla(0,0%,100%,0))',
  '.Mui-focusVisible &': {
    outline: '2px auto rgba(19,124,189,.6)',
    outlineOffset: 2,
  },
  'input:hover ~ &': {
    backgroundColor: theme.palette.mode === 'dark' ? '#30404d' : '#ebf1f5',
  },
  'input:disabled ~ &': {
    boxShadow: 'none',
    background:
      theme.palette.mode === 'dark' ? 'rgba(57,75,89,.5)' : 'rgba(206,217,224,.5)',
  },
  '&:before': {
    display: 'block',
    width: 16,
    height: 16,
    backgroundImage: 'radial-gradient(#13233c,#13233c 28%,transparent 32%)',
    content: '""',
  },
}));

const BpCheckedIcon = styled(BpIcon)({
  backgroundColor: '#137cbd',
  backgroundImage: 'linear-gradient(180deg,hsla(0,0%,100%,.1),hsla(0,0%,100%,0))',
  '&:before': {
    display: 'block',
    width: 16,
    height: 16,
    backgroundImage: 'radial-gradient(#fff,#fff 28%,transparent 32%)',
    content: '""',
  },
  'input:hover ~ &': {
    backgroundColor: '#106ba3',
  },
});

export const BpRadio = (props) => {
  return (
    <Radio
      sx={{
        '&:hover': {
          bgcolor: 'transparent',
        },
      }}
      disableRipple
      color="default"
      checkedIcon={<BpCheckedIcon />}
      icon={<BpIcon />}
      {...props}
    />
  );
}

export const groupBy = (array, key) => {
  return array.reduce((result, currentValue) => {
    (result[currentValue[key]] = result[currentValue[key]] || []).push(
      currentValue
    );
    return result;
  }, {});
};

export const getDate = (d) => {
  let dt = new Date(Number(d) * 1000).toDateString().split(' ');
  let date = `${dt[2]} ${dt[1]}`;
  let h = new Date(Number(d) * 1000).getHours();
  let m = new Date(Number(d) * 1000).getMinutes();
  h = String(h).length == 1 ? `0${h}` : h;
  m = String(m).length == 1 ? `0${m}` : m;
  let time = `${h}:${m}`;
  return {
    date,
    time,
    week: dt[0]
  }
};

export const getScore = (ss, scores, time, id) => {
  let score = '', timer = '';
  if (ss) {
    ss = ss.split(',');
    ss = ss[ss.length - 1];
    if (id === 3) {
      ss = ss.replace('/', ':');
    } else {
      ss = ss.replace('-', ':');
    }
  }

  if (scores) {
    score = '(';
    for (let i in scores) {
      score += scores[i].home + ':';
      score += scores[i].away + ' - ';
    }
    score = score.slice(0, -3);
    score += ')';
  }

  if (time) {
    timer = `${time.tm}'`;
  }
  return { ss, scores: score, timer }
}

export const getMkName = (sId, mk) => {
  let mkName = '', odName = {};
  if (mk === '3_4') {
    return 'Draw No Bet (Cricket)';
  }
  if (sId === 1) {
    switch (mk) {
      case '1_1':
        mkName = '1X2';
        odName = { home_od: 'W1', draw_od: 'Draw', away_od: 'W2' }
        break;
      case '1_2':
        mkName = 'Asian Handicap';
        break;
      case '1_3':
        mkName = 'O/U';
        break;
      case '1_4':
        mkName = 'Asian Corners';
        break;
      case '1_5':
        mkName = '1st Half Asian Handicap';
        break;
      case '1_6':
        mkName = '1st Half Goal Line';
        break;
      case '1_7':
        mkName = '1st Half Asian Corners';
        break;
      case '1_8':
        mkName = 'Half Time Result';
        break;
    }
  } else if (sId === 18) {
    switch (mk) {
      case '18_1':
        mkName = 'Money Line';
        break;
      case '18_2':
        mkName = 'Spread';
        break;
      case '18_3':
        mkName = 'Total Points';
        break;
      case '18_4':
        mkName = 'Money Line (Half)';
        break;
      case '18_5':
        mkName = 'Spread (Half)';
        break;
      case '18_6':
        mkName = 'Total Points (Half)';
        break;
      case '18_7':
        mkName = 'Quarter - Winner (2-Way)';
        break;
      case '18_8':
        mkName = 'Quarter - Handicap';
        break;
      case '18_9':
        mkName = 'Quarter - Total (2-Way)';
        break;
    }
  } else {
    switch (mk) {
      case `${sId}_1`:
        mkName = 'Match Winner 2-Way';
        break;
      case `${sId}_2`:
        mkName = 'Asian Handicap';
        break;
      case `${sId}_3`:
        mkName = 'Over/Under';
        break;
      case `${sId}_4`:
        mkName = 'Who will win';
        break;
    }
  }
  return mkName;
} 