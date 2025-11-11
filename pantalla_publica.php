<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Turnos | Pantalla Pública</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            overflow: hidden; /* Ocultar barras de desplazamiento para full screen */
        }
        .container-fluid {
            min-height: 100vh;
        }
        .main-display {
            background-color: #007bff; /* Fondo Azul Corporativo */
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            height: 100%; 
        }
        .turno-numero {
            font-size: 15vw; /* Tamaño de fuente muy grande */
            font-weight: bold;
            line-height: 1;
        }
        .escritorio-info {
            font-size: 5vw;
        }
        .video-area {
            /* Calcula el alto para que el video y la lista ocupen el 100% del espacio */
            height: calc(100vh - 40px - 200px); 
            min-height: 300px;
            background-color: #343a40;
            border-radius: 10px;
            overflow: hidden;
        }
        #videoPlayer {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .list-card-container {
             height: 200px; 
             overflow-y: hidden;
        }
        /* Estilo para el botón de activación de audio */
        #audioActivator {
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            z-index: 9999;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 3rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <button id="audioActivator" onclick="activarAudio()">TOCA AQUÍ PARA INICIAR EL SISTEMA DE AUDIO</button>

    <div class="container-fluid p-4">
        <div class="row h-100">
            <div class="col-8 d-flex flex-column">
                <div class="video-area shadow-lg mb-4">
                    <video id="videoPlayer" autoplay loop muted>
                        <source src="assets/videos/video_empresa_1.mp4" type="video/mp4">
                        Tu navegador no soporta el tag de video.
                    </video>
                </div>
                
                <div class="card shadow-sm mt-auto list-card-container">
                    <div class="card-header bg-dark text-white">Últimos Turnos Llamados</div>
                    <div class="card-body p-0">
                        <ul id="lista-turnos" class="list-group list-group-flush">
                            <li class="list-group-item text-muted text-center">Cargando turnos recientes...</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-4">
                <div class="main-display shadow-lg h-100 d-flex flex-column justify-content-center">
                    <p class="mb-0 escritorio-info">TURNO ACTUAL</p>
                    <div id="turno-actual" class="turno-numero text-warning">--</div>
                    <p class="mb-0 escritorio-info">Diríjase a:</p>
                    <div id="escritorio-actual" class="escritorio-info text-white">--</div>
                    <p id="area-actual" class="mt-3 fs-5">--</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const turnoDisplay = document.getElementById('turno-actual');
        const escritorioDisplay = document.getElementById('escritorio-actual');
        const areaDisplay = document.getElementById('area-actual');
        const videoPlayer = document.getElementById('videoPlayer');
        const listaTurnos = document.getElementById('lista-turnos');
        const activatorButton = document.getElementById('audioActivator');
        
        // --- VARIABLES GLOBALES PARA CONTROL DE AUDIO Y CONCURRENCIA ---
        let ultimaFirmaAnunciada = ''; 
        const colaDeAnuncios = []; 
        let isSpeaking = false; 
        let isQueueEmpty = true; // Flag para el estado de la cola
        // ----------------------------------------

        // --- CONTROL DE REPRODUCCIÓN DE VIDEOS ---
        const videoPlaylist = [
            'assets/videos/video_empresa_1.mp4',
            'assets/videos/publicidad_2.mp4',
        ];
        let currentVideoIndex = 0;
        
        function loadNextVideo() {
            if (videoPlaylist.length === 0) return;
            currentVideoIndex = (currentVideoIndex + 1) % videoPlaylist.length;
            videoPlayer.src = videoPlaylist[currentVideoIndex];
            videoPlayer.load();
            videoPlayer.play().catch(e => console.error("Error al reproducir el siguiente video:", e));
        }

        if (videoPlaylist.length > 0) {
            videoPlayer.src = videoPlaylist[0];
            videoPlayer.addEventListener('ended', loadNextVideo);
        }
        // ----------------------------------------

        // --- FUNCIÓN DE ACTIVACIÓN DE AUDIO ---
        function activarAudio() {
            const utterance = new SpeechSynthesisUtterance('a'); 
            utterance.volume = 0;
            window.speechSynthesis.speak(utterance);

            activatorButton.classList.add('hidden');
            
            videoPlayer.play().catch(e => console.log("Video iniciado después de la interacción.")); 
        }
        // ----------------------------------------


        // ==========================================================
        // LÓGICA DE COLA DE AUDIO Y SINCRONIZACIÓN VISUAL
        // ==========================================================

        /**
         * Función que añade un anuncio a la cola.
         */
        function agregarAnuncio(anuncioTexto, visualData) {
            colaDeAnuncios.push({ text: anuncioTexto, visual: visualData });
            if (!isSpeaking) {
                procesarColaDeAnuncios();
            }
        }

        /**
         * Función que procesa los elementos de la cola de anuncios de audio.
         */
        function procesarColaDeAnuncios() {
            if (colaDeAnuncios.length === 0) {
                isSpeaking = false;
                // Si la cola de anuncios termina, actualizamos el display visual
                if (isQueueEmpty) {
                     turnoDisplay.textContent = '--';
                     escritorioDisplay.textContent = 'En Espera';
                     areaDisplay.textContent = '¡Bienvenido!';
                }
                return; 
            }

            isSpeaking = true;
            const anuncio = colaDeAnuncios.shift(); // Saca el primer elemento de la cola
            
            // 1. SINCRONIZACIÓN VISUAL CRÍTICA: Mostrar el turno justo antes del audio
            turnoDisplay.textContent = anuncio.visual.turno;
            escritorioDisplay.textContent = anuncio.visual.ubicacion + ' ' + anuncio.visual.escritorio; 
            areaDisplay.textContent = '(' + anuncio.visual.area + ')';
            
            // 2. Efecto visual (flash)
            turnoDisplay.classList.add('bg-warning', 'text-dark');
            setTimeout(() => {
                turnoDisplay.classList.remove('bg-warning', 'text-dark');
            }, 5000); 

            // 3. Reproducir Audio
            const utterance = new SpeechSynthesisUtterance(anuncio.text);
            utterance.lang = 'es-ES';
            utterance.rate = 1.0; 

            utterance.onend = function() {
                isSpeaking = false;
                // 4. Llama al siguiente elemento en la cola después de una pausa
                setTimeout(procesarColaDeAnuncios, 500); 
            };
            
            utterance.onerror = function(e) {
                console.error('Error de TTS durante el anuncio:', e);
                isSpeaking = false;
                procesarColaDeAnuncios(); 
            };

            window.speechSynthesis.speak(utterance);
        }
        
        // ==========================================================
        
        /**
         * Actualiza la lista de los últimos turnos llamados.
         */
        function actualizarLista() {
            fetch('get_ultimos_llamados.php')
                .then(response => {
                     if (!response.ok) throw new Error('Error de red al obtener la lista.');
                     return response.json();
                 })
                .then(data => {
                    let html = '';
                    
                    if (data && data.length > 0) {
                        data.forEach((item, index) => {
                            // Resalta el turno más reciente si coincide con el llamado actual
                            const isCurrent = (index === 0 && item.numero_completo === turnoDisplay.textContent && turnoDisplay.textContent !== '--') ? 'list-group-item-warning fw-bold' : '';
                            
                            // Define el término de ubicación o usa "Escritorio" por defecto
                            const ubicacion = item.termino_ubicacion ? item.termino_ubicacion : 'Escritorio';
                            
                            html += `
                                <li class="list-group-item d-flex justify-content-between align-items-center ${isCurrent}">
                                    <strong class="fs-5">${item.numero_completo}</strong> 
                                    <span class="badge bg-primary rounded-pill fs-6">${ubicacion} ${item.escritorio_llamado}</span>
                                </li>
                            `;
                        });
                    } else {
                        html = '<li class="list-group-item text-muted text-center">La lista de turnos recientes ha sido borrada.</li>';
                    }
                    
                    listaTurnos.innerHTML = html;
                })
                .catch(error => console.error('Error al actualizar la lista de turnos:', error));
        }

        /**
         * FUNCIÓN AUXILIAR CRÍTICA: Obtiene el estado visual del último turno LLAMADO (o vacío)
         * Se usa para mantener el display principal en el estado correcto cuando NO hay anuncios en cola.
         */
        function fetchLatestCalledStatus() {
            // Solo actualiza el display si NO estamos hablando (para no cortar el turno actual en voz)
            if (isSpeaking) return; 

            fetch('get_ultimo_llamado.php')
                .then(response => response.json())
                .then(data => {
                    if (data && data.numero_completo) {
                        const terminoUbicacion = data.termino_ubicacion ? data.termino_ubicacion : 'Escritorio';
                        
                        // Actualizar Display (Visual)
                        turnoDisplay.textContent = data.numero_completo;
                        escritorioDisplay.textContent = terminoUbicacion + ' ' + data.escritorio_llamado; 
                        areaDisplay.textContent = '(' + data.nombre_area + ')';
                        
                    } else {
                        // Si ya no hay un turno 'LLAMADO' (e.g., fue marcado atendido)
                        turnoDisplay.textContent = '--';
                        escritorioDisplay.textContent = 'En Espera';
                        areaDisplay.textContent = '¡Bienvenido!';
                    }
                });
        }


        /**
         * Función principal (Actualiza la pantalla y alimenta la cola de audio)
         */
        function actualizarPantallaConcurrente() {
            fetch('get_turnos_a_anunciar.php') // Endpoint que devuelve la lista de pendientes de anunciar
                .then(response => {
                    if (!response.ok) throw new Error('Error de red al obtener la lista de anuncios.');
                    return response.json();
                })
                .then(response => {
                    isQueueEmpty = true; // Asumimos que la cola está vacía
                    
                    if (response.success && response.data.length > 0) {
                        
                        isQueueEmpty = false; // Hay al menos un turno activo
                        
                        // 1. Procesar TODOS los turnos en el lote
                        response.data.forEach(data => {
                            const nuevoTurno = data.numero_completo;
                            const nuevoEscritorio = data.escritorio_llamado;
                            const terminoUbicacion = data.termino_ubicacion ? data.termino_ubicacion : 'Escritorio'; 

                            // Crear el objeto de datos visuales y de texto
                            const partes = nuevoTurno.split('-'); 
                            const prefijo = partes[0]; 
                            const numeroLimpio = parseInt(partes[1], 10);
                            const anuncioTexto = `Turno ${prefijo} ${numeroLimpio}. Diríjase a ${terminoUbicacion} ${nuevoEscritorio}.`;
                            
                            const visualData = {
                                turno: nuevoTurno,
                                escritorio: nuevoEscritorio,
                                ubicacion: terminoUbicacion,
                                area: data.nombre_area || 'Llamada Activa' // Usamos el nombre de área
                            };

                            // Añadir a la cola de audio
                            if (activatorButton.classList.contains('hidden')) {
                                agregarAnuncio(anuncioTexto, visualData); 
                            }
                        });
                    } 
                    
                    // 2. Mantenimiento del Estado Visual y Lista
                    actualizarLista(); // Refresca la lista de últimos llamados
                    fetchLatestCalledStatus(); // Mantiene el estado visual principal

                })
                .catch(error => {
                    console.error('Error al obtener datos del display concurrente:', error);
                });
        }


        // === INICIALIZACIÓN ===
        // El timer principal ahora llama a la función de concurrencia
        setInterval(actualizarPantallaConcurrente, 3000); 
        actualizarPantallaConcurrente(); 
    </script>
</body>
</html>