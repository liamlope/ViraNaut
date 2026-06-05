window.MIRZA_DEMO_DATA = {
  user: {
    balance: 1250000,
    name: 'کاربر نمونه',
    phone: '0912***4567',
    count_order: 3,
    count_payment: 12,
    group_type: 'عادی',
    time_join: '1404/01/15',
  },
  countries: [
    { id: 'de', name: 'آلمان', flag: '🇩🇪' },
    { id: 'nl', name: 'هلند', flag: '🇳🇱' },
    { id: 'tr', name: 'ترکیه', flag: '🇹🇷' },
    { id: 'fi', name: 'فنلاند', flag: '🇫🇮' },
  ],
  categories: [
    { id: 1, name: 'اقتصادی' },
    { id: 2, name: 'پرفروش' },
    { id: 3, name: 'نامحدود' },
  ],
  products: [
    { id: 1, name: 'پلن ۱ ماهه · ۵۰ گیگ', price: 89000, days: 30, gb: 50, badge: 'پرفروش', cat: 'اقتصادی' },
    { id: 2, name: 'پلن ۳ ماهه · ۱۵۰ گیگ', price: 229000, days: 90, gb: 150, badge: '۲۰٪ تخفیف', cat: 'پرفروش' },
    { id: 3, name: 'پلن ۶ ماهه · ۳۰۰ گیگ', price: 399000, days: 180, gb: 300, badge: 'ویژه', cat: 'نامحدود' },
    { id: 4, name: 'پلن تست · ۱ گیگ', price: 0, days: 1, gb: 1, badge: 'رایگان', cat: 'اقتصادی' },
  ],
  services: [
    { id: 's1', name: 'vpn_de_8842', status: 'فعال', status_cls: 'ok', expire: '1405/04/12', traffic: '32 / 50 GB', panel: 'آلمان' },
    { id: 's2', name: 'vpn_nl_1201', status: 'هشدار حجم', status_cls: 'warn', expire: '1405/02/28', traffic: '88 / 100 GB', panel: 'هلند' },
    { id: 's3', name: 'vpn_tr_0091', status: 'منقضی', status_cls: 'off', expire: '1404/12/01', traffic: '0 / 30 GB', panel: 'ترکیه' },
  ],
  banners: [
    { title: 'تخفیف بهاره', text: 'خرید ۳ ماهه با ۲۰٪ تخفیف', cta: 'مشاهده پلن‌ها' },
    { title: 'سرور جدید فنلاند', text: 'پینگ پایین برای بازی', cta: 'خرید از فنلاند' },
  ],
};
