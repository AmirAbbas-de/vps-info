
document.addEventListener("DOMContentLoaded", function() {
    const rows = document.querySelectorAll("tbody tr");
    const now = new Date();

    rows.forEach(row => {
        const dateElement = row.querySelector(".paid-till");
        if (dateElement) {
            // استخراج تاریخ از متن "Paid till 6/10/2026"
            const dateText = dateElement.innerText.replace("Paid till ", "").trim();
            const expiryDate = new Date(dateText);

            // محاسبه اختلاف به میلی‌ثانیه و تبدیل به روز
            const diffInTime = expiryDate.getTime() - now.getTime();
            const diffInDays = Math.ceil(diffInTime / (1000 * 3600 * 24));

            // اعمال کلاس بر اساس روزهای باقی‌مانده
            if (diffInDays <= 2) {
                row.classList.add("warning-urgent"); // قرمز
            } else if (diffInDays <= 4) {
                row.classList.add("warning-soon");   // نارنجی
            } else if (diffInDays <= 7) {
                row.classList.add("warning-week");   // زرد
            }
        }
    });
});
