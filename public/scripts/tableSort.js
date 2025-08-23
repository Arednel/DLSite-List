document.addEventListener("DOMContentLoaded", () => {
    const headers = document.querySelectorAll(".list-table-header th[data-column]");
    let currentSort = {
        column: null,
        order: "desc"
    };

    headers.forEach(header => {
        header.addEventListener("click", () => {
            const column = header.dataset.column;
            const table = header.closest("table");
            const rows = Array.from(table.querySelectorAll(
                "tbody tr:not(.list-table-header)"));

            // toggle order
            if (currentSort.column === column) {
                currentSort.order = currentSort.order === "asc" ? "desc" : "asc";
            } else {
                currentSort.column = column;
                currentSort.order = "desc"; // default first click = descending
            }

            // find index of clicked column
            const colIndex = Array.from(header.parentNode.children).indexOf(header);

            rows.sort((a, b) => {
                const aText = a.children[colIndex]?.innerText.trim() || "";
                const bText = b.children[colIndex]?.innerText.trim() || "";

                let aVal, bVal;

                if (column.toLowerCase() === "score") {
                    // Score: treat "-" as 0, else parse as int
                    aVal = aText === "-" ? 0 : parseInt(aText, 10);
                    bVal = bText === "-" ? 0 : parseInt(bText, 10);
                } else if (column.toLowerCase() === "title") {
                    // Title: extract RJ number
                    const aMatch = aText.match(/rj(\d+)/i);
                    const bMatch = bText.match(/rj(\d+)/i);
                    aVal = aMatch ? parseInt(aMatch[1], 10) : 0;
                    bVal = bMatch ? parseInt(bMatch[1], 10) : 0;
                } else {
                    // Fallback: numeric if possible, else text compare
                    aVal = isNaN(aText) ? aText.toLowerCase() : parseFloat(aText) ||
                        0;
                    bVal = isNaN(bText) ? bText.toLowerCase() : parseFloat(bText) ||
                        0;
                }

                if (aVal < bVal) return currentSort.order === "asc" ? -1 : 1;
                if (aVal > bVal) return currentSort.order === "asc" ? 1 : -1;
                return 0;
            });

            // reattach sorted rows
            const tbody = table.querySelector("tbody");
            tbody.querySelectorAll("tr:not(.list-table-header)").forEach(r => r.remove());
            rows.forEach(r => tbody.appendChild(r));

            // update sort icons
            headers.forEach(h => h.querySelector(".sort-icon").textContent = "⇅");
            header.querySelector(".sort-icon").textContent = currentSort.order === "asc" ?
                "↑" : "↓";
        });
    });
});