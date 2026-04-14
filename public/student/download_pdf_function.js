// Download PDF - Updated for A4 size
async function downloadPDF() {
    // Collect all data first
    for (let i = 1; i <= 6; i++) {
        collectStepData(i);
    }

    try {
        const response = await fetch('resume_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'generate_pdf',
                resumeData: resumeData
            })
        });

        if (response.ok) {
            const htmlContent = await response.text();

            // Open in new window for printing to PDF (A4 size)
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.write(htmlContent);
            printWindow.document.close();

            // Wait for content to load then trigger print dialog
            printWindow.onload = function () {
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                }, 250);
            };
        } else {
            alert('❌ Error generating PDF');
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}
