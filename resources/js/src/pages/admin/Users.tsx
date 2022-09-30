import { useEffect, useState } from 'react';

import Paper from '@mui/material/Paper';
import Table from '@mui/material/Table';
import TableRow from '@mui/material/TableRow';
import Collapse from '@mui/material/Collapse';
import Checkbox from '@mui/material/Checkbox';
import FormGroup from '@mui/material/FormGroup';
import TableBody from '@mui/material/TableBody';
import TableHead from '@mui/material/TableHead';
import TableCell from '@mui/material/TableCell';
import IconButton from '@mui/material/IconButton';
import FormControlLabel from '@mui/material/FormControlLabel';
import TableContainer from '@mui/material/TableContainer';
import MenuItem from '@mui/material/MenuItem';
import Select, { SelectChangeEvent } from '@mui/material/Select';

import SearchIcon from '@mui/icons-material/Search';
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';

import { HStack, OutInput, TblCell } from 'components/Base';

const ranges = [
    'Current week',
    'Last week',
    'Two weeks ago',
    'Tree weeks ago',
    'Four weeks ago',
];

const conditions = [
    'All',
    'Pendding',
    'Win',
    'Lost',
];

const Users = () => {
    const createData = (
        id: string,
        date: string,
        sport: string,
        desc: string,
        odd: number,
        stake: number,
        winRes: number,
        result: number,
        status: string,
    ) => {
        return { id, date, sport, desc, odd, stake, winRes, result, status, };
    }

    const rows = [
        createData('1234567', '08/11/2022', 'Soccer', 'Real Mardrid-Australia', 2.3, 100, 230, 150, 'Pendding'),
        createData('12321', '08/10/2022', 'Table Tennis', 'Real Mardrid-Australia', 2.3, 100, 230, 150, 'Win'),
        createData('12321653', '08/10/2022', 'Basketball', 'Real Mardrid-Australia', 2.3, 100, 230, 150, 'Lose'),
    ];

    const Row = (props: { row: ReturnType<typeof createData> }) => {
        const { row } = props;
        const [open, setOpen] = useState(false);

        return (
            <>
                <TableRow sx={{ '& > *': { borderBottom: 'unset' } }}>
                    <TblCell align="center">
                        {row.id}
                    </TblCell>
                    <TblCell align="center" >{row.date}</TblCell>
                    <TblCell align="center" >{row.sport}</TblCell>
                    <TblCell align="center" >{row.desc}</TblCell>
                    <TblCell sx={{ width: (theme) => theme.spacing(1) }}>
                        <IconButton
                            aria-label="expand row"
                            size="small"
                            onClick={() => setOpen(!open)}
                        >
                            {open ? <KeyboardArrowUpIcon /> : <KeyboardArrowDownIcon />}
                        </IconButton>
                    </TblCell>
                    <TblCell align="center" >{row.odd}</TblCell>
                    <TblCell align="center" >{row.stake}</TblCell>
                    <TblCell align="center" >{row.winRes}</TblCell>
                    <TblCell align="center">{row.result}</TblCell>
                    <TblCell align="center" >{row.status}</TblCell>
                </TableRow>
                <TableRow>
                    <TableCell style={{ padding: 0 }} colSpan={12}>
                        <Collapse in={open} timeout="auto" unmountOnExit>
                            <Table>
                                <TableBody sx={{ bgcolor: (theme) => theme.palette.background.default }}>
                                    {
                                        [1, 2, 3,].map((idx: number) => (
                                            <TableRow sx={{ '& > *': { borderBottom: 'unset' } }} key={idx}>
                                                <TblCell align="right" sx={{ width: '10%' }}>
                                                    {row.id}
                                                </TblCell>
                                                <TblCell align="center" sx={{ width: '9%' }}>{row.date}</TblCell>
                                                <TblCell align="center" sx={{ width: '8%' }}>{row.sport}</TblCell>
                                                <TblCell align="center" sx={{ minWidth: '10%' }}>{row.desc}</TblCell>
                                                <TblCell sx={{ width: (theme) => theme.spacing(1) }}>
                                                    <IconButton size='small'>
                                                        <InfoOutlinedIcon />
                                                    </IconButton>
                                                </TblCell>
                                                <TblCell align="center" sx={{ width: '5%' }}>{row.odd}</TblCell>
                                                <TblCell align="center" sx={{ width: '8%' }}>{row.stake}</TblCell>
                                                <TblCell align="center" sx={{ width: '8%' }}>{row.winRes}</TblCell>
                                                <TblCell align="center" sx={{ width: '8%' }}>{row.result}</TblCell>
                                                <TblCell align="center" sx={{ width: '8%' }}>{row.status}</TblCell>
                                            </TableRow>
                                        ))
                                    }
                                </TableBody>
                            </Table>
                        </Collapse>
                    </TableCell>
                </TableRow>
            </>
        );
    }

    const [range, setRange] = useState<string>('');
    const [condition, setCondition] = useState<string>('');

    const changeRange = (event: SelectChangeEvent<typeof range>) => {
        const {
            target: { value },
        } = event;
        setRange(value);
    };

    useEffect(() => {
        setRange(ranges[0]);
        setCondition(conditions[0]);
    }, []);

    return (
        <>
            <HStack
                sx={{
                    py: 1,
                    px: 2,
                    justifyContent: 'space-between',
                    bgcolor: (theme) => theme.palette.secondary.dark
                }}
            >
                <HStack>
                    <FormGroup>
                        <FormControlLabel control={<Checkbox defaultChecked />} label="Online Users" />
                    </FormGroup>
                    <Select
                        value={range}
                        onChange={changeRange}
                        input={<OutInput />}
                        sx={{ mr: 1 }}
                        MenuProps={{
                            anchorOrigin: {
                                vertical: 'bottom',
                                horizontal: 'right',
                            },
                            transformOrigin: {
                                vertical: 'top',
                                horizontal: 'right',
                            },
                            PaperProps: {
                                sx: {
                                    borderRadius: 0,
                                    width: (theme) => theme.spacing(20),
                                },
                            },
                        }}
                    >
                        {ranges.map((item: string, idx: number) => (
                            <MenuItem
                                key={idx}
                                value={item}
                            >
                                {item}
                            </MenuItem>
                        ))}
                    </Select>
                </HStack>

                <HStack>
                    <OutInput sx={{ height: (theme) => theme.spacing(4.3) }} placeholder="User Name" />
                    <IconButton
                        sx={{
                            borderRadius: 0,
                            height: (theme) => theme.spacing(4.3),
                            width: (theme) => theme.spacing(4.3),
                            bgcolor: (theme) => theme.palette.secondary.main,
                            [`&:hover`]: {
                                bgcolor: (theme) => theme.palette.secondary.light
                            }
                        }}>
                        <SearchIcon />
                    </IconButton>
                </HStack>
            </HStack>

            <TableContainer component={Paper} sx={{ borderRadius: 0 }}>
                <Table aria-label="collapsible table">
                    <TableHead>
                        <TableRow sx={{ bgcolor: (theme) => theme.palette.background.paper }}>
                            <TblCell sx={{ width: (theme) => theme.spacing(1) }}>
                                <IconButton size='small'>
                                    <InfoOutlinedIcon />
                                </IconButton>
                            </TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>User Id</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Name</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Status</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Credit</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Open Bet</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Close Bet</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Total</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Total Net</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Agent Commission</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Platform Commission</TblCell>
                            <TblCell align="center" sx={{ width: '10%' }}>Enter</TblCell>
                        </TableRow>
                    </TableHead>
                    {/* <TableBody>
                        {rows.map((row, idx) => (
                            <Row key={idx} row={row} />
                        ))}
                    </TableBody> */}
                </Table>
            </TableContainer>
        </>
    );
};

export default Users;