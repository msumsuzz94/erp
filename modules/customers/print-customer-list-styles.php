<style>
@media print {
    .no-print, .card-header, .btn, .navbar, .sidebar, #accordionSidebar, .topbar, .page-footer, footer {
        display: none !important;
    }
    
    .table {
        font-size: 12px;
    }
    
    tr {
        page-break-inside: avoid;
    }
    
    body::before {
        content: "Customer List";
        display: block;
        font-size: 24px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 20px;
    }
}
</style>
