export function formatLastTrainingDay(startsOn, durationMonths) {
    const [year, month, day] = startsOn.split('-').map(Number);
    const targetMonth = new Date(Date.UTC(year, month - 1 + durationMonths, 1));
    const lastDay = new Date(Date.UTC(
        targetMonth.getUTCFullYear(), targetMonth.getUTCMonth() + 1, 0,
    )).getUTCDate();
    const finalDate = new Date(Date.UTC(
        targetMonth.getUTCFullYear(), targetMonth.getUTCMonth(), Math.min(day, lastDay),
    ));

    if (day <= lastDay) {
        finalDate.setUTCDate(finalDate.getUTCDate() - 1);
    }

    return [finalDate.getUTCDate(), finalDate.getUTCMonth() + 1, finalDate.getUTCFullYear()]
        .map((part) => String(part).padStart(2, '0')).join('/');
}
