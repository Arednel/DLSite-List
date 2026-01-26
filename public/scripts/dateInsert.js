document.addEventListener('DOMContentLoaded', () => {
  const setToday = (prefix) => {
    const now = new Date();
    const month = String(now.getMonth() + 1);
    const day = String(now.getDate());
    const year = String(now.getFullYear());

    const monthSelect = document.getElementById(`${prefix}_date_month`);
    const daySelect = document.getElementById(`${prefix}_date_day`);
    const yearSelect = document.getElementById(`${prefix}_date_year`);

    if (monthSelect) monthSelect.value = month;
    if (daySelect) daySelect.value = day;
    if (yearSelect) yearSelect.value = year;
  };

  const startLink = document.getElementById('start_date_insert_today');
  if (startLink) {
    startLink.addEventListener('click', (event) => {
      event.preventDefault();
      setToday('add_start');
    });
  }

  const endLink = document.getElementById('end_date_insert_today');
  if (endLink) {
    endLink.addEventListener('click', (event) => {
      event.preventDefault();
      setToday('add_finish');
    });
  }
});
