const localArea = 'По Москве и МО';
const localTime = 'В течение суток';
const freeFrom = '150\u00a0000\u00a0₽';

export const deliveryContent = {
    localArea,
    localTime,
    freeFrom,
    local: `${localArea} — ${localTime.toLowerCase()}, бесплатно при заказе от ${freeFrom}`,
    regions: 'В регионы отправляем любой транспортной компанией. Сроки и стоимость уточнит менеджер.',
};
