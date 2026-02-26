<?php
/**
 * Modal de Anuncios - Carrusel
 * Include reutilizable para todos los dashboards
 * Ubicación: includes/anuncios/modal_anuncios.php
 * 
 * USO: incluir en cualquier dashboard después del </main> o antes del cierre de body:
 *   <?php include __DIR__ . '/../includes/anuncios/modal_anuncios.php'; ?>    (desde dashboard/)
 *   <?php include __DIR__ . '/../../includes/anuncios/modal_anuncios.php'; ?> (desde dashboard/subcarpeta/)
 * 
 * BOTÓN: agregar en el .user-info del top-navbar:
 *   <button class="btn-anuncios-trigger" onclick="new bootstrap.Modal(document.getElementById('modalAnuncios')).show()" title="Anuncios">
 *       <i class="bi bi-megaphone-fill"></i>
 *   </button>
 */

// Obtener anuncios activos
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
        $pdo = conectarDB();
    }
    
    $stmt = $pdo->prepare("
        SELECT id, titulo, contenido, icono, color_fondo
        FROM anuncios_plataforma
        WHERE activo = 1
          AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
          AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
        ORDER BY orden ASC, fecha_creacion DESC
    ");
    $stmt->execute();
    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $anuncios = [];
}

$total_anuncios = count($anuncios);

// Control de auto-mostrar: 1 vez por sesión PHP
$mostrar_auto = false;
if ($total_anuncios > 0 && !isset($_SESSION['anuncios_mostrados'])) {
    $mostrar_auto = true;
    $_SESSION['anuncios_mostrados'] = true;
}
?>

<?php if ($total_anuncios > 0): ?>

<!-- ============================================
     ESTILOS DEL MODAL DE ANUNCIOS
     ============================================ -->
<style>
/* --- Botón trigger en navbar --- */
.btn-anuncios-trigger {
    position: relative;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
    animation: anuncio-pulse 2s ease-in-out infinite;
}

.btn-anuncios-trigger:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.5);
}

.btn-anuncios-trigger .badge-count {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #dc3545;
    color: #fff;
    font-size: 0.6rem;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border: 2px solid #fff;
}

@keyframes anuncio-pulse {
    0%, 100% { box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4); }
    50% { box-shadow: 0 2px 20px rgba(245, 158, 11, 0.7); }
}

/* --- Modal --- */
.modal-anuncios .modal-dialog {
    max-width: 560px;
}

.modal-anuncios .modal-content {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-anuncios .modal-header {
    background: linear-gradient(-135deg, #07492E, #0E6847);
    color: #fff;
    border: none;
    padding: 1rem 1.25rem;
    position: relative;
}

.modal-anuncios .modal-header .modal-title {
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-anuncios .modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}

.modal-anuncios .modal-header .btn-close:hover {
    opacity: 1;
}

.modal-anuncios .modal-body {
    padding: 0;
    background: #f8f9fa;
}

/* --- Carrusel --- */
.anuncio-slide {
    padding: 1.5rem;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.anuncio-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: #fff;
    margin-bottom: 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.anuncio-titulo {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.anuncio-contenido {
    font-size: 0.88rem;
    color: #444;
    line-height: 1.6;
    max-width: 460px;
}

.anuncio-contenido strong {
    color: #0E6847;
    font-weight: 600;
}

/* --- Indicadores del carrusel --- */
.carousel-indicators-anuncios {
    display: flex;
    justify-content: center;
    gap: 8px;
    padding: 0.75rem 0 0.25rem;
    margin: 0;
    list-style: none;
}

.carousel-indicators-anuncios button {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid #0E6847;
    background: transparent;
    cursor: pointer;
    padding: 0;
    transition: all 0.3s ease;
}

.carousel-indicators-anuncios button.active {
    background: #0E6847;
    transform: scale(1.2);
}

/* --- Controles del carrusel --- */
.anuncio-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 1.5rem 1rem;
}

.anuncio-nav-btn {
    background: #0E6847;
    color: #fff;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.anuncio-nav-btn:hover {
    background: #07492E;
    transform: scale(1.1);
}

.anuncio-nav-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.anuncio-counter {
    font-size: 0.78rem;
    color: #666;
    font-weight: 500;
}

/* --- Footer del modal --- */
.modal-anuncios .modal-footer {
    border-top: 1px solid #e9ecef;
    padding: 0.75rem 1.25rem;
    background: #f8f9fa;
    justify-content: center;
}

.btn-cerrar-anuncios {
    background: linear-gradient(-135deg, #07492E, #0E6847);
    color: #fff;
    border: none;
    padding: 0.45rem 2rem;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-cerrar-anuncios:hover {
    background: linear-gradient(-135deg, #1B3321, #04CF85);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(14, 104, 71, 0.3);
}

/* --- Responsive --- */
@media (max-width: 576px) {
    /* ... responsive styles (sin cambios) ... */
}

/* --- Modo Oscuro --- */
body[data-theme="dark"] .modal-anuncios .modal-content {
    background-color: #2d2d2d;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

body[data-theme="dark"] .modal-anuncios .modal-body {
    background: #1a1a1a;
}

body[data-theme="dark"] .anuncio-titulo {
    color: #e0e0e0;
}

body[data-theme="dark"] .anuncio-contenido {
    color: #b0b0b0;
}

body[data-theme="dark"] .anuncio-contenido strong {
    color: #04CF85;
}

body[data-theme="dark"] .carousel-indicators-anuncios button {
    border-color: #04CF85;
}

body[data-theme="dark"] .carousel-indicators-anuncios button.active {
    background: #04CF85;
}

body[data-theme="dark"] .anuncio-counter {
    color: #b0b0b0;
}

body[data-theme="dark"] .anuncio-nav-btn {
    background: #04CF85;
    color: #1a1a1a;
}

body[data-theme="dark"] .anuncio-nav-btn:hover {
    background: #06e998;
}

body[data-theme="dark"] .modal-anuncios .modal-footer {
    background: #1a1a1a;
    border-top-color: #404040;
}

body[data-theme="dark"] .btn-cerrar-anuncios {
    background: linear-gradient(-135deg, #04CF85, #0E6847);
}

body[data-theme="dark"] .btn-cerrar-anuncios:hover {
    background: linear-gradient(-135deg, #06e998, #04CF85);
}
</style>

<!-- ============================================
     MODAL DE ANUNCIOS
     ============================================ -->
<div class="modal fade modal-anuncios" id="modalAnuncios" tabindex="-1" aria-labelledby="modalAnunciosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            
            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnunciosLabel">
                    <i class="bi bi-megaphone-fill"></i>
                    Anuncios Importantes
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body con carrusel -->
            <div class="modal-body">
                
                <!-- Slides -->
                <div id="anunciosCarruselContainer">
                    <?php foreach ($anuncios as $index => $anuncio): ?>
                    <div class="anuncio-slide" data-slide="<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'flex' : 'none'; ?>;">
                        <div class="anuncio-icon-wrapper" style="background: <?php echo htmlspecialchars($anuncio['color_fondo']); ?>;">
                            <i class="bi <?php echo htmlspecialchars($anuncio['icono']); ?>"></i>
                        </div>
                        <h6 class="anuncio-titulo"><?php echo htmlspecialchars($anuncio['titulo']); ?></h6>
                        <div class="anuncio-contenido">
                            <?php echo $anuncio['contenido']; // Permite HTML controlado ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Indicadores -->
                <?php if ($total_anuncios > 1): ?>
                <div class="carousel-indicators-anuncios" id="anunciosIndicadores">
                    <?php for ($i = 0; $i < $total_anuncios; $i++): ?>
                    <button data-goto="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>"></button>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>

                <!-- Controles -->
                <?php if ($total_anuncios > 1): ?>
                <div class="anuncio-controls">
                    <button class="anuncio-nav-btn" id="anuncioPrev" title="Anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <span class="anuncio-counter">
                        <span id="anuncioActual">1</span> / <?php echo $total_anuncios; ?>
                    </span>
                    <button class="anuncio-nav-btn" id="anuncioNext" title="Siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <?php endif; ?>

            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn-cerrar-anuncios" data-bs-dismiss="modal">
                    Entendido
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT DEL CARRUSEL
     ============================================ -->
<script>
window.addEventListener('load', function() {
    const totalSlides = <?php echo $total_anuncios; ?>;
    const autoShow = <?php echo $mostrar_auto ? 'true' : 'false'; ?>;
    let currentSlide = 0;
    let autoPlayInterval = null;

    // --- Funciones del carrusel ---
    function showSlide(index) {
        document.querySelectorAll('.anuncio-slide').forEach(s => s.style.display = 'none');
        const slide = document.querySelector(`.anuncio-slide[data-slide="${index}"]`);
        if (slide) {
            slide.style.display = 'flex';
            slide.style.animation = 'fadeIn 0.4s ease';
        }
        document.querySelectorAll('.carousel-indicators-anuncios button').forEach((btn, i) => {
            btn.classList.toggle('active', i === index);
        });
        const counter = document.getElementById('anuncioActual');
        if (counter) counter.textContent = index + 1;
        currentSlide = index;
    }

    function nextSlide() {
        showSlide((currentSlide + 1) % totalSlides);
    }

    function prevSlide() {
        showSlide((currentSlide - 1 + totalSlides) % totalSlides);
    }

    // --- Event listeners ---
    const btnNext = document.getElementById('anuncioNext');
    const btnPrev = document.getElementById('anuncioPrev');
    
    if (btnNext) btnNext.addEventListener('click', function() { nextSlide(); resetAutoPlay(); });
    if (btnPrev) btnPrev.addEventListener('click', function() { prevSlide(); resetAutoPlay(); });

    document.querySelectorAll('.carousel-indicators-anuncios button').forEach(btn => {
        btn.addEventListener('click', function() {
            showSlide(parseInt(this.dataset.goto));
            resetAutoPlay();
        });
    });

    // --- Auto-play (cada 6 segundos) ---
    function startAutoPlay() {
        if (totalSlides > 1) {
            autoPlayInterval = setInterval(nextSlide, 6000);
        }
    }

    function resetAutoPlay() {
        clearInterval(autoPlayInterval);
        startAutoPlay();
    }

    // --- Mostrar automáticamente (controlado por sesión PHP) ---
    const modalEl = document.getElementById('modalAnuncios');
    
    if (modalEl) {
        if (autoShow) {
            setTimeout(function() {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }, 800);
        }

        // Iniciar/detener autoplay con el modal
        modalEl.addEventListener('shown.bs.modal', startAutoPlay);
        modalEl.addEventListener('hidden.bs.modal', function() {
            clearInterval(autoPlayInterval);
            showSlide(0); // Reset al cerrar
        });
    }

    // --- Animación fade ---
    if (!document.getElementById('anuncioFadeStyle')) {
        const style = document.createElement('style');
        style.id = 'anuncioFadeStyle';
        style.textContent = '@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }';
        document.head.appendChild(style);
    }
});
</script>

<?php endif; ?>