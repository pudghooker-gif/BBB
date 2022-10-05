import { createReducer } from '@reduxjs/toolkit';
import { auth } from './actions';

const initialState = {
};

export default createReducer(initialState, (builder) =>
    builder
        .addCase(auth, (state, { payload }) => {
            return {
                ...state,
                ...payload
            };
        })
);
