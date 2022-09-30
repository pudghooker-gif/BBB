import { useEffect, useState } from 'react';
import Collapse from '@mui/material/Collapse';
import IconButton from '@mui/material/IconButton';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Paper from '@mui/material/Paper';
import MenuItem from '@mui/material/MenuItem';
import Select, { SelectChangeEvent } from '@mui/material/Select';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import KeyboardArrowUpIcon from '@mui/icons-material/KeyboardArrowUp';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';

import { BoxBorder, HStack, OutInput, TblCell } from 'components/Base';
import { ManageHead } from 'components/Part';

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

const BetList = () => {
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

    const changeCondtion = (event: SelectChangeEvent<typeof condition>) => {
        const {
            target: { value },
        } = event;
        setCondition(value);
    };

    useEffect(() => {
        setRange(ranges[0]);
        setCondition(conditions[0]);
    }, []);

    return (
        <BoxBorder sx={{ mt: 2 }}>
            <ManageHead title={'Bet List'} />

            <HStack
                sx={{
                    py: 1,
                    px: 2,
                    justifyContent: 'flex-start',
                    bgcolor: (theme) => theme.palette.secondary.dark
                }}
            >
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
                <Select
                    value={condition}
                    onChange={changeCondtion}
                    input={<OutInput />}
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
                    {conditions.map((item: string, idx: number) => (
                        <MenuItem
                            key={idx}
                            value={item}
                        >
                            {item}
                        </MenuItem>
                    ))}
                </Select>
            </HStack>

            <TableContainer component={Paper} sx={{ borderRadius: 0 }}>
                <Table aria-label="collapsible table">
                    <TableHead>
                        <TableRow sx={{ bgcolor: (theme) => theme.palette.background.paper }}>
                            <TblCell align="center" sx={{ width: '10%' }}>Bet Id</TblCell>
                            <TblCell align="center" sx={{ width: '9%' }}>Bet Date</TblCell>
                            <TblCell align="center" sx={{ width: '8%' }}>Sport</TblCell>
                            <TblCell align="center" sx={{ minWidth: '10%' }}>Description</TblCell>
                            <TblCell sx={{ width: (theme) => theme.spacing(1) }}>
                                <IconButton size='small'>
                                    <InfoOutlinedIcon />
                                </IconButton>
                            </TblCell>
                            <TblCell align="center" sx={{ width: '5%' }}>Odds</TblCell>
                            <TblCell align="center" sx={{ width: '8%' }}>Stack</TblCell>
                            <TblCell align="center" sx={{ width: '8%' }}>Win Result</TblCell>
                            <TblCell align="center" sx={{ width: '8%' }}>Result</TblCell>
                            <TblCell align="center" sx={{ width: '8%' }}>Status</TblCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {rows.map((row, idx) => (
                            <Row key={idx} row={row} />
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
        </BoxBorder>);
};

export default BetList;