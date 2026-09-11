function parts(value) {
    if (!value) {
        return { date: '-', time: '', full: '-' };
    }

    const text = String(value).trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (!match) {
        return { date: text, time: '', full: text };
    }

    const date = `${match[1]}-${match[2]}-${match[3]}`;
    const time = `${match[4]}:${match[5]}:${match[6] || '00'}`;

    return { date, time, full: `${date} ${time}` };
}

export function formatDateTime(value) {
    return parts(value).full;
}

export function formatDatePart(value) {
    return parts(value).date;
}

export function formatTimePart(value) {
    return parts(value).time || '-';
}
