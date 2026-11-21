@if (empty($preview))
<script type="text/php">
if (isset($pdf)) {
    $texto = "Página {PAGE_NUM} de {PAGE_COUNT}";
    $fuente = $fontMetrics->getFont("Helvetica", "normal");
    $tamano = 7;
    $ancho = $fontMetrics->getTextWidth($texto, $fuente, $tamano);
    $pdf->page_text(
        $pdf->get_width() - $ancho - 28,
        $pdf->get_height() - 24,
        $texto,
        $fuente,
        $tamano,
        [0.6, 0.6, 0.6]
    );
}
</script>
@endif
