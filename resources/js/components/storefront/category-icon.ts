import {
    AppWindow, Battery, Cable, Camera, Cpu, Fan, Grid2X2, HardDrive, Headphones,
    Keyboard, Laptop, MemoryStick, Monitor, Mouse, Network, PcCase, Plug,
    Printer, Projector, Router, ScanBarcode, Server, ShieldCheck, ShoppingBag,
    Tablet, Usb, Video, Webcam, Antenna, Wifi, EthernetPort, Wrench,
    CircuitBoard, Gpu, RectangleHorizontal, Phone, Presentation, Tv,
    SprayCan, Droplets, Copy, Shredder, BatteryCharging, Activity,
    FileText, KeyRound, type LucideIcon,
} from 'lucide-react';
import taxonomy from '../../../../config/catalog-taxonomy.json';

const groups: Record<string, LucideIcon> = {
    computers: Laptop, 'servers-storage': Server, network: Network,
    components: Cpu, peripherals: Monitor, printing: Printer,
    power: Battery, software: AppWindow,
};
const icons: Record<string, LucideIcon> = {
    'Серверы': Server, 'Компьютеры': Monitor,
    'Компьютеры и ноутбуки': Laptop, 'Серверы и хранение данных': Server,
    'Сетевое оборудование': Network, 'Комплектующие': Cpu,
    'Мониторы и периферия': Monitor, 'Печать и расходные материалы': Printer,
    'Электропитание': Battery, 'Программное обеспечение': AppWindow,
};
for (const group of taxonomy) {
    const icon = icons[group.name] ?? groups[group.code] ?? Grid2X2;
    for (const child of group.children) icons[child.name] = icon;
}
Object.assign(icons, {
    'Мини-ПК': PcCase, 'Моноблоки': Monitor, 'Настольные компьютеры': PcCase,
    'Ноутбуки': Laptop, 'Накопители для серверов и NAS': HardDrive,
    'Серверная оперативная память': MemoryStick, 'Системы хранения данных': HardDrive,
    'Маршрутизаторы': Router, 'Точки доступа и Wi-Fi-системы': Wifi,
    'Сетевые кабели': Cable, 'Видеонаблюдение': Camera,
    'SSD': HardDrive, 'Жёсткие диски': HardDrive, 'Внешние накопители': HardDrive,
    'Блоки питания': Plug, 'Корпуса': PcCase, 'Оперативная память': MemoryStick,
    'Охлаждение': Fan, 'Флеш-накопители и карты памяти': Usb,
    'Веб-камеры': Webcam, 'Гарнитуры': Headphones, 'Док-станции и USB-хабы': Usb,
    'Интерактивные панели': Tablet, 'Кабели и переходники': Cable,
    'Клавиатуры': Keyboard, 'Комплекты клавиатура и мышь': Keyboard,
    'Мыши и трекпады': Mouse, 'Коврики для мыши': RectangleHorizontal, 'Проекторы': Projector,
    'Сумки и рюкзаки для ноутбуков': ShoppingBag, 'Оборудование видеоконференций': Video,
    'Сканеры штрихкодов': ScanBarcode, 'Сетевые фильтры и удлинители': Plug,
    'Антивирусы и защита': ShieldCheck, 'Операционные системы': AppWindow,
    'Серверные комплектующие': CircuitBoard,
    'Антенны': Antenna, 'Коммутаторы': Network,
    'СКС и монтажные принадлежности': Wrench, 'Сетевые адаптеры и модемы': EthernetPort,
    'Видеокарты': Gpu, 'Материнские платы': CircuitBoard, 'Процессоры': Cpu,
    'Принадлежности для установки накопителей': Wrench,
    'Крепления и стойки': Wrench, 'Мониторы': Monitor,
    'Офисная телефония': Phone, 'Подставки для ноутбуков': Laptop,
    'Профессиональные дисплеи': Monitor, 'Светодиодные экраны и модули': Grid2X2,
    'Средства ухода за техникой': SprayCan, 'Системы гостиничного телевидения': Tv,
    'Проекционные экраны': Presentation,
    'Запчасти и аксессуары для печати': Wrench, 'Картриджи и тонеры': Droplets,
    'Многофункциональные устройства': Copy, 'Принтеры': Printer,
    'Уничтожители документов': Shredder,
    'Аккумуляторы для ИБП': Battery, 'Источники бесперебойного питания': BatteryCharging,
    'Мониторинг и монтаж ИБП': Activity,
    'Офисные приложения': FileText, 'Серверное ПО и лицензии доступа': KeyRound,
    'ПО для видеоконференций': Video,
});

/** Same category icon in navigation, cards, search and the product gallery. */
export function categoryIcon(category: string | null | undefined): LucideIcon {
    const name = category?.trim();
    return name && Object.hasOwn(icons, name) ? icons[name] : Grid2X2;
}
