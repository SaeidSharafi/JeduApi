const instructorData = {
    image: "/test/profile.png", name: "ملیحه محمدی", description: " لورم ایپسوم متن ساختگی با تولید سادگی نامفهوم...",
}

const CertificateData = {
    status: "تکمیل نشده",
}

const locationData = {
    address: "پردیس اصلی - ساختمان شماره 2", map_url: null
}

const sessions = [{
    id: 1, title: "جلسه اول", topic: "اصول پایه", date: "شنبه 12 شهریور", time: "10:00 تا 13:00",
}, {
    id: 2, title: "جلسه دوم", topic: "اصول پایه", date: "شنبه 12 شهریور", time: "10:00 تا 13:00",
}, {
    id: 3, title: "جلسه سوم", topic: "اصول پایه", date: "شنبه 12 شهریور", time: "10:00 تا 13:00",
}, {
    id: 4, title: "جلسه چهارم", topic: "اصول پایه", date: "شنبه 12 شهریور", time: "10:00 تا 13:00",
},];

const courses = [{
    id: 1,
    title: "ری‌اکت برای مبتدی‌ها",
    name: "جلسه دوم: ساخت دشبورد تعاملی",
    time: "چهارشنبه 28 شهریور - ساعت 18",
    status: "active",
    rate: 4.5,
    author: "محمد رضایی",
    image: "/test/course1.png",
    type: "آنلاین",
    onlineMode: "normal",
}, {
    id: 2,
    title: "CSS پیشرفته",
    name: "جلسه دوم: ساخت دشبورد تعاملی",
    time: "15 جلسه - 56 ساعت",
    status: "disabled",
    rate: 4.2,
    author: "سارا احمدی",
    image: "/test/course2.png",
    type: "آفلاین",
}, {
    id: 3,
    title: "مبانی UI/UX",
    name: "جلسه دوم: ساخت دشبورد تعاملی",
    time: "15 جلسه - 56 ساعت",
    status: "ended",
    rate: 4.8,
    author: "علی مرادی",
    image: "/test/course3.png",
    type: "حضوری",
}, {
    id: 4,
    title: "Next.js مدل‌سیستم",
    name: "جلسه دوم: ساخت دشبورد تعاملی",
    time: "15 جلسه - 56 ساعت",
    status: "active",
    rate: 5.0,
    author: "فاطمه موسوی",
    image: "/test/course4.jpg",
    type: "آنلاین",
    onlineMode: "model-system",
},];

const userData = {
    name: "ثبت تیکت", image: "/test/pfp1.jpg", gmail: "test@gmail.com", balance: "120,000"
};

const tickets = [{
    id: 1,
    title: "مشکل در پرداخت",
    description: "پرداخت من ناموفق بوده اما مبلغ از حساب کسر شده است.",
    avatar: "/icons/dots/dot.png",
}, {
    id: 2,
    title: "دسترسی به دوره",
    description: "بعد از خرید، دوره در پنل من نمایش داده نمی‌شود.",
    avatar: "/icons/dots/dot.png",
},];

const notifications = [{
    id: 1,
    text: ` دوره شما پس از {highlight} به پایان میرسد`,
    highlight: "3 روز",
    date: "1404/11/10",
    status: "warning",
    actionText: "مشاهده تقویم آموزشی"
}, {
    id: 2,
    text: "پرداخت شما با موفقیت انجام شد",
    highlight: "",
    date: "1404/11/08",
    status: "success",
    actionText: "مشاهده فاکتور",
}, {
    id: 3,
    text: "پرداخت شما با موفقیت انجام شد",
    highlight: "",
    date: "1404/11/08",
    status: "success",
    actionText: "مشاهده فاکتور",
}, {
    id: 4,
    text: "پرداخت شما با موفقیت انجام نشد",
    highlight: "",
    date: "1404/11/08",
    status: "failure",
    actionText: "مشاهده فاکتور",
},];

const certificates = [{
    id: 1, title: "دوره icdl مقدماتی", author: "محسن مردانی", image: "/license.jpg", passed: true,
}, {
    id: 2, title: "دوره icdl مقدماتی", author: "محسن مردانی", image: "/license.jpg", passed: true,
}, {
    id: 3, title: "دوره icdl مقدماتی", author: "محسن مردانی", image: "/license.jpg", passed: true,
},];

export const filesData = {
    name: "فایل های دوره icdl مقدماتی", items: [{
        id: 1, title: "جزوه پنجم", fileType: "RAR", fileSize: "250MB",
    }, {
        id: 2, title: "جزوه چهارم", fileType: "PDF", fileSize: "120MB",
    }, {
        id: 3, title: "جزوه سوم", fileType: "DOCX", fileSize: "90MB",
    },],
};

export const testsData = {
    name: "دوره پایتون", items: [{
        id: 1, title: "آزمون اول", description: "آزمون اصول اولیه کامپیوتر", state: "ready",
    }, {
        id: 2, title: "آزمون دوم", description: "نمره نهایی : 60/100", state: "sent",
    }, {
        id: 3, title: "آزمون سوم", description: "آزمون نهایی دوره", state: "pending",
    },],
};
