import axios from 'axios';

export const api = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export function getErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
    return error?.response?.data?.error || error?.message || fallback;
}
