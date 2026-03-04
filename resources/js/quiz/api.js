import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export const getErrorMessage = (error, fallbackMessage) => {
    return error?.response?.data?.message || fallbackMessage;
};
