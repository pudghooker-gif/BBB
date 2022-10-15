import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import Axios from '../providers/request';
import { useToasts } from 'react-toast-notifications';

import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import IconButton from '@mui/material/IconButton';
import RadioGroup from '@mui/material/RadioGroup';
import FormControlLabel from '@mui/material/FormControlLabel';

import DeleteIcon from '@mui/icons-material/Delete';

import { HStack, BpRadio, getMkName } from './Base';

import classNames from 'classnames';

import { SET_BETSLIP, SET_MULTI_CALC, SET_BET_TYPE, SET_USER_DATA } from '../redux/type';

const betAction = async (user_id, betType, data, multiCalc, matchs) => {
  let rdata = await Axios('post', '/sports/bet', { user_id, bet: data, betType, multi: multiCalc, matchs });
  return rdata;
}

const Single = ({ data, clear, setStake }) => {
  return (
    <>
      {
        Object.keys(data).map((key, idx) => (
          <Box sx={{ mb: 1 }} key={idx}>
            <Box sx={{ py: 1.25, mb: 1, bgcolor: '#fff', borderRadius: 2 }}>
              <HStack sx={{ mb: 1, px: 1.25, alignItems: 'center' }}>
                <Button className='slip-Odd'>{data[key].odd}</Button>
                <Typography sx={{ color: '#096dff', fontSize: '11px', fontWeight: '700' }}>{`${getMkName(data[key].sId, data[key].mk)}, ${data[key].odt}`}</Typography>
                <IconButton className='close-odd' onClick={() => clear(key)}>
                  <DeleteIcon sx={{ fontSize: '16px' }} />
                </IconButton>
              </HStack>
              <HStack className='sliip-teams'>
                <Box>
                  <Typography sx={{ color: '#000', fontWeight: 600, fontSize: '13px' }}>{data[key].home.name}</Typography>
                  <Typography sx={{ color: '#000', fontWeight: 600, fontSize: '13px' }}>{data[key].away.name}</Typography>
                </Box>
              </HStack>
              <HStack sx={{ px: 1.25 }} justifyContent='space-between' alignItems='center'>
                <HStack>
                  <i className={classNames("sports-icon", `icon-${data[key].sportName.toLowerCase().replaceAll(' ', '-')}`, "betslip-icon")}></i>
                  <Typography component='span' sx={{ color: '#94a6cd', fontWeight: 500, fontSize: '11px', lineHeight: 1.2, pl: 1 }}>
                    {data[key].league.name}
                  </Typography>
                </HStack>
                {
                  data[key].isLive ?
                    <HStack className='live-mark'>
                      <Box className='live-dot' />
                      <Typography component='span' sx={{ fontSize: '12px' }}>LIVE</Typography>
                    </HStack> : null
                }
              </HStack>
            </Box>
            <HStack justifyContent='space-between'>
              <Typography sx={{ color: '#0dc35d', pb: .25, fontWeight: 600, textAlign: 'left', fontSize: '12px' }}>Possible profit</Typography>
              <Typography sx={{ color: '#0dc35d', pb: .5, fontWeight: 600, textAlign: 'right', fontSize: '12px' }}>{`${data[key].profit.toFixed(2)} USD`}</Typography>
            </HStack>
            <Box>
              <TextField variant="outlined" className='enter-stake' placeholder='Bet amount' type='number' onChange={(e) => setStake(key, e.target.value, data[key].odd)} />
            </Box>
          </Box>
        ))
      }
    </>
  )
}

const Multi = ({ data, clear, setStake, multiCalc }) => {
  return (
    <Box sx={{ mb: 1 }}>
      {
        Object.keys(data).map((key, idx) => (
          <Box className='express-slip' key={idx}>
            <HStack sx={{ pb: 1, mx: 1.25, alignItems: 'center', pt: 1.25, bgcolor: 'white' }}>
              <Button className='slip-Odd'>{data[key].odd}</Button>
              <Typography sx={{ color: '#096dff', fontSize: '11px', fontWeight: '700' }}>{`${getMkName(data[key].sId, data[key].mk)}, ${data[key].odt}`}</Typography>
              <IconButton className='close-odd' onClick={() => clear(key)}>
                <DeleteIcon sx={{ fontSize: '16px' }} />
              </IconButton>
            </HStack>
            <HStack className='sliip-teams' sx={{ bgcolor: 'white' }}>
              <Box>
                <Typography sx={{ color: '#000', fontWeight: 600, fontSize: '13px' }}>{data[key].home.name}</Typography>
                <Typography sx={{ color: '#000', fontWeight: 600, fontSize: '13px' }}>{data[key].away.name}</Typography>
              </Box>
            </HStack>
            <HStack sx={{ mx: 1.25, pb: 1.25, bgcolor: 'white' }} justifyContent='space-between' alignItems='center'>
              <HStack>
                <i className={classNames("sports-icon", `icon-soccer`, "betslip-icon")}></i>
                <Typography component='span' sx={{ color: '#94a6cd', fontWeight: 500, fontSize: '11px', lineHeight: 1.2, pl: 1 }}>
                  {data[key].league.name}
                </Typography>
              </HStack>
              {
                data[key].isLive ?
                  <HStack className='live-mark'>
                    <Box className='live-dot' />
                    <Typography component='span' sx={{ fontSize: '12px' }}>LIVE</Typography>
                  </HStack> : null
              }
            </HStack>
          </Box>
        ))
      }
      <Box className='total-odd'>
        <Button className='btn'>
          {`${Number(multiCalc.odd).toFixed(2)} Total Coefficient`}
        </Button>
      </Box>
      <HStack justifyContent='space-between'>
        <Typography sx={{ color: '#0dc35d', pb: .25, fontWeight: 600, textAlign: 'right', fontSize: '12px' }}>Possible profit</Typography>
        <Typography sx={{ color: '#0dc35d', pb: .5, fontWeight: 600, textAlign: 'right', fontSize: '12px' }}>{`${Number(multiCalc.profit).toFixed(2)} USD`}</Typography>
      </HStack>
      <Box>
        <TextField variant="outlined" className='enter-stake' placeholder='Bet amount' type='number' onChange={(e) => setStake('key', e.target.value)} />
      </Box>
    </Box>
  )
}

const BetSlip = () => {
  const dispatch = useDispatch();
  const { addToast } = useToasts();
  const { betType, betSlip, multiCalc } = useSelector(state => state.sports);
  const user = useSelector((state) => state.user);

  const filterMulti = (data) => {
    let newSlip = {};
    for (let item of data) {
      let key = item.slice(-1)[0];
      newSlip[key] = betSlip[key];
    }
    dispatch({ type: SET_BETSLIP, data: newSlip });

    let odd = 1;
    for (let k in newSlip) {
      odd *= Number(Number(newSlip[k].odd).toFixed(2));
    }
    let profit = odd * multiCalc.stake;
    dispatch({ type: SET_MULTI_CALC, data: { ...multiCalc, odd, profit } });
  }

  const multiSlipCheck = () => {
    let isDup = [],
      eventKey = Object.keys(betSlip);
    for (let i = 0; i < eventKey.length; i++) {
      let dup = [eventKey[i]];
      let f = eventKey[i].split('-')[0];
      for (let j = i + 1; j < eventKey.length; j++) {
        let s = eventKey[j].split('-')[0];
        if (f == s) {
          dup.push(eventKey[j]);
          eventKey.splice(j, 1);
          --j;
        }
      }
      isDup.push(dup);
    }
    if (isDup.length === 1) return;
    filterMulti(isDup);
    dispatch({ type: SET_BET_TYPE, data: event.target.value });
  }

  const switchType = (event) => {
    multiSlipCheck(event);
  };

  const clearAll = () => {
    dispatch({ type: SET_BETSLIP, data: {} });
  }

  const clear = (key) => {
    let oldSlip = betSlip, newSlip = {};
    for (let i in oldSlip) {
      if (i === key) continue;
      newSlip[i] = oldSlip[i];
    }
    dispatch({ type: SET_BETSLIP, data: newSlip });
  }

  const setStake = (key, val, odd) => {
    if (betType === 'single') {
      dispatch({ type: SET_BETSLIP, data: { ...betSlip, [key]: { ...betSlip[key], stake: val, profit: Number(odd) * Number(val) } } });
    } else {
      dispatch({ type: SET_MULTI_CALC, data: { ...multiCalc, stake: val, profit: (Number(multiCalc.odd) * Number(val)).toFixed(2) } });
    }
  }

  const bet = async () => {
    if (user.isAuth) {
      let data = [];
      let matchs = [];
      for (let i in betSlip) {
        let item = betSlip[i];
        let obj = {};
        obj.sportId = item.sId;
        obj.eventId = item.eId;
        if (matchs.indexOf(item.eId) === -1) {
          matchs.push(item.eId);
        }
        obj.odds = Number(item.odd);
        if (Number(item.stake) < 10) {
          addToast('Please check stake amount. Minimum is 10.', {
            appearance: 'warning',
            autoDismiss: true,
          })
          return;
        }
        obj.stake = Number(item.stake);
        obj.potential = Number(item.profit);
        obj.marketId = item.mk;
        obj.handicap = item.handicap.split(',')[0];
        obj.oddType = item.ot.split('_')[0];
        obj.home = item.home.name;
        obj.away = item.away.name;
        obj.league = item.league.name;
        obj.sportName = item.sportName;
        data.push(obj);
      }

      let rdata = await betAction(user.id, betType, data, multiCalc, matchs);
      if (rdata.status) {
        dispatch({ type: SET_USER_DATA, data: { ...user, ...rdata.data[0] } });
        addToast('Success!', {
          appearance: 'success',
          autoDismiss: true,
        })
        clearAll();
      } else {
        addToast(rdata.msg, {
          appearance: 'error',
          autoDismiss: true,
        })
      }
    } else {
      addToast('Please login.', {
        appearance: 'info',
        autoDismiss: true,
      })
    }
  }

  useEffect(() => {
    if (Object.keys(betSlip).length < 2) {
      dispatch({ type: SET_BET_TYPE, data: 'single' });
    }
  }, [betSlip]);

  return (
    <Box className='betslip' sx={{ mb: { xs: '60px' } }}>
      <HStack className='bet-tabbar'>
        <Box className='slip-title'>
          BETSLIP
          {
            Object.keys(betSlip).length ?
              <Typography component='span' className='slip-count'>{Object.keys(betSlip).length}</Typography>
              : null
          }
        </Box>
      </HStack>
      <Box>
        <RadioGroup value={betType} onChange={switchType} sx={{ flexDirection: 'row', justifyContent: 'space-between', width: '100%', px: 1, mb: 1 }}>
          <FormControlLabel value="single" control={<BpRadio sx={{ padding: 0, mr: 1 }} />} label="Ordinary" sx={{ '& .MuiFormControlLabel-label': { margin: 0, fontWeight: 700, fontSize: "12px" } }} />
          <FormControlLabel value="multi" control={<BpRadio sx={{ padding: 0, mr: 1 }} />} label="Express" sx={{ '& .MuiFormControlLabel-label': { margin: 0, fontWeight: 700, fontSize: "12px" } }} />
        </RadioGroup>
        <Box>
          {
            betType === 'single' ?
              <Single {...{ data: betSlip, clear, setStake }} /> : <Multi {...{ data: betSlip, clear, multiCalc, setStake }} />
          }
        </Box>
        <Box>
          <HStack sx={{ mb: 1, alignItems: 'center' }}>
            {
              Object.keys(betSlip).length ?
                <Button sx={{ height: '28px', borderRadius: 2, ml: 'auto', bgcolor: '#79ccf929' }} onClick={() => clearAll()}>
                  <DeleteIcon sx={{ fontSize: '20px' }} />
                  <Typography sx={{ fontSize: '14px', ml: 1, textTransform: 'capitalize' }}>Clear All</Typography>
                </Button    > :
                <Box sx={{ width: '100%', borderRadius: 2, bgcolor: 'white', p: 1 }}>
                  <HStack sx={{ bgcolor: '#d0daf3', py: .75, px: 1.25, borderRadius: 2 }}>
                    <Typography sx={{ color: '#405484', fontSize: 12 }}>0.00
                      Total coefficient</Typography>
                  </HStack>
                </Box>
            }
          </HStack>
          <Button onClick={() => bet()} sx={{ borderRadius: 2, width: '100%', color: '#090f1e', bgcolor: '#ffe036', boxShadow: '0 2px 24px 0 #ffca094d', fontWeight: 700, '&:hover': { bgcolor: '#ffe036' } }}>
            Make Bet
          </Button>
        </Box>
      </Box>
    </Box>
  );
}

export default BetSlip;