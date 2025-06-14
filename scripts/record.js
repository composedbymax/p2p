class AudioCaptureModal {
    constructor() {
        this.isOpen = false;
        this.isRecording = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.audioStream = null;
        this.recordings = [];
        this.init();
    }
    init() {
        this.injectCSS();
        this.injectHTML();
        this.bindEvents();
    }
    injectCSS() {
        const style = document.createElement('style');
        style.textContent = `
            .audio-modal-overlay{position: fixed;top: 0;left: 0;width: 100%;height: 100%;backdrop-filter: blur(4px);z-index: 9998;visibility: hidden;transition: all 0.3s ease;}
            .audio-modal-overlay.active{opacity: 1;visibility: visible;}
            .audio-modal{position: fixed;top: 0;right: -400px;width: 380px;height: 100vh;background: var(--gradient);color: var(--text);box-shadow: -5px 0 20px var(--shade);transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);z-index: 9999;overflow-y: auto;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;}
            .audio-modal.open{right: 0;}
            .audio-modal-header{padding: 24px 20px 16px;border-bottom: 1px solid var(--dark2);position: sticky;top: 0;background: inherit;backdrop-filter: blur(10px);}
            .audio-modal-title{font-size: 22px;font-weight: 600;margin: 0 0 8px 0;color: var(--accent);}
            .audio-modal-subtitle{font-size: 14px;color: var(--text2);margin: 0;}
            .audio-modal-close{position: absolute;top: 20px;right: 20px;background: none;border: none;color: var(--text2);font-size: 24px;cursor: pointer;padding: 4px;border-radius: 4px;transition: all 0.2s ease;}
            .audio-modal-close:hover{color: var(--text);background: var(--dark2);}
            .audio-modal-content{padding: 24px 20px;}
            .audio-capture-section{margin-bottom: 32px;}
            .audio-capture-controls{display: flex;flex-direction: column;gap: 12px;margin-bottom: 20px;}
            .audio-btn{padding: 12px 20px;border: none;border-radius: 8px;font-size: 14px;font-weight: 500;cursor: pointer;transition: all 0.2s ease;display: flex;align-items: center;justify-content: center;gap: 8px;}
            .audio-btn-primary{background: linear-gradient(135deg, var(--accent), var(--accenth));color: var(--black);}
            .audio-btn-primary:hover:not(:disabled){transform: translateY(-1px);box-shadow: 0 4px 12px var(--accentg);}
            .audio-btn-secondary{background: var(--dark2);color: var(--text);border: 1px solid var(--dark3);}
            .audio-btn-secondary:hover:not(:disabled){background: var(--dark3);}
            .audio-btn-danger{background: var(--red);color: var(--text);}
            .audio-btn-danger:hover:not(:disabled){background: var(--redh);transform: translateY(-1px);}
            .audio-btn:disabled{opacity: 0.5;cursor: not-allowed;transform: none !important;box-shadow: none !important;}
            .recording-indicator{display: none;align-items: center;gap: 8px;padding: 12px 16px;background: rgba(220, 53, 69, 0.1);border: 1px solid var(--red);border-radius: 8px;margin-bottom: 16px;}
            .recording-indicator.active{display: flex;}
            .recording-dot{width: 8px;height: 8px;background: var(--red);border-radius: 50%;animation: pulse 1.5s infinite;}
            @keyframes pulse{0%, 100%{opacity: 1;}50%{opacity: 0.3;}}
            .recording-time{font-family: monospace;font-size: 14px;color: var(--red);}
            .recordings-section{border-top: 1px solid var(--dark2);padding-top: 24px;}
            .recordings-title{font-size: 18px;font-weight: 600;margin: 0 0 16px 0;color: var(--text1);}
            .recording-item{background: var(--dark1);border: 1px solid var(--dark2);border-radius: 8px;padding: 16px;margin-bottom: 12px;transition: all 0.2s ease;}
            .recording-item:hover{background: var(--dark2);border-color: var(--dark3);}
            .recording-name{font-size: 14px;font-weight: 500;margin: 0 0 8px 0;color: var(--text);}
            .recording-meta{font-size: 12px;color: var(--text2);margin-bottom: 12px;}
            .recording-controls{display: flex;gap: 8px;flex-wrap: wrap;}
            .recording-controls .audio-btn{padding: 6px 12px;font-size: 12px;flex: 1;min-width: 70px;}
            .download-options{display: none;flex-direction: column;gap: 4px;margin-top: 8px;}
            .download-options.active{display: flex;}
            .download-options .audio-btn{padding: 4px 8px;font-size: 11px;}
            .no-recordings{text-align: center;color: var(--text2);font-style: italic;padding: 32px 16px;}
            .audio-modal-trigger{position: fixed;top: 50%;right: 20px;transform: translateY(-50%);background: var(--gradient3);color: var(--accent);border: 2px solid var(--accent);width: 50px;height: 50px;border-radius: 50%;cursor: pointer;box-shadow: 0 4px 12px var(--accentg);transition: all 0.3s ease;z-index: 9997;display: flex;align-items: center;justify-content: center;}
            .audio-modal-trigger:hover{transform: translateY(-50%) scale(1.1);background: var(--accent);color: var(--black);}
            .audio-modal-trigger svg{width: 20px;height: 20px;}
            @media (max-width: 480px){.audio-modal{width: 100%;right: -100%;}
            .audio-modal-trigger{right: 15px;}}
        `;
        document.head.appendChild(style);
    }

    injectHTML() {
        const modalHTML = `
            <button class="audio-modal-trigger" id="audioModalTrigger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 5L6 9H2v6h4l5 4V5z"/>
                    <path d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
                    <path d="M19.07 4.93a9 9 0 0 1 0 14.14"/>
                </svg>
            </button>
            <div class="audio-modal-overlay" id="audioModalOverlay"></div>
            <div class="audio-modal" id="audioModal">
                <div class="audio-modal-header">
                    <h2 class="audio-modal-title">Audio Capture</h2>
                    <p class="audio-modal-subtitle">Record microphone audio</p>
                    <button class="audio-modal-close" id="audioModalClose">×</button>
                </div>
                <div class="audio-modal-content">
                    <div class="audio-capture-section">
                        <div class="recording-indicator" id="recordingIndicator">
                            <div class="recording-dot"></div>
                            <span>Recording</span>
                            <span class="recording-time" id="recordingTime">00:00</span>
                        </div>
                        <div class="audio-capture-controls">
                            <button class="audio-btn audio-btn-primary" id="startRecording">
                                Start Recording
                            </button>
                            <button class="audio-btn audio-btn-danger" id="stopRecording" disabled>
                                Stop Recording
                            </button>
                            <button class="audio-btn audio-btn-secondary" id="clearRecordings">
                                Clear All
                            </button>
                        </div>
                    </div>
                    <div class="recordings-section">
                        <h3 class="recordings-title">Recordings</h3>
                        <div id="recordingsList">
                            <div class="no-recordings">No recordings yet</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    bindEvents() {
        const trigger = document.getElementById('audioModalTrigger');
        const overlay = document.getElementById('audioModalOverlay');
        const closeBtn = document.getElementById('audioModalClose');
        const startBtn = document.getElementById('startRecording');
        const stopBtn = document.getElementById('stopRecording');
        const clearBtn = document.getElementById('clearRecordings');
        trigger.addEventListener('click', () => this.open());
        overlay.addEventListener('click', () => this.close());
        closeBtn.addEventListener('click', () => this.close());
        startBtn.addEventListener('click', () => this.startRecording());
        stopBtn.addEventListener('click', () => this.stopRecording());
        clearBtn.addEventListener('click', () => this.clearRecordings());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    }
    bindRecordingControls() {
        const recordingsList = document.getElementById('recordingsList');
        recordingsList.removeEventListener('click', this.handleRecordingAction);
        this.handleRecordingAction = (e) => {
            if (e.target.matches('[data-action]')) {
                const action = e.target.dataset.action;
                const id = parseInt(e.target.dataset.id);
                const format = e.target.dataset.format;
                switch (action) {
                    case 'play':
                        this.playRecording(id);
                        break;
                    case 'download':
                        this.downloadRecording(id, format);
                        break;
                    case 'delete':
                        this.deleteRecording(id);
                        break;
                    case 'toggle-downloads':
                        this.toggleDownloadOptions(id);
                        break;
                }
            }
        };
        recordingsList.addEventListener('click', this.handleRecordingAction);
    }
    open() {
        const modal = document.getElementById('audioModal');
        const overlay = document.getElementById('audioModalOverlay');
        overlay.classList.add('active');
        modal.classList.add('open');
        this.isOpen = true;
        this.requestPermissions();
    }
    close() {
        const modal = document.getElementById('audioModal');
        const overlay = document.getElementById('audioModalOverlay');
        overlay.classList.remove('active');
        modal.classList.remove('open');
        this.isOpen = false;
        if (this.isRecording) {
            this.stopRecording();
        }
    }
    async requestPermissions() {
        try {
            await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (error) {
            console.warn('Microphone permission denied:', error);
        }
    }
    async startRecording() {
        try {
            const constraints = { audio: true };
            this.audioStream = await navigator.mediaDevices.getUserMedia(constraints);
            this.mediaRecorder = new MediaRecorder(this.audioStream, {
                mimeType: 'audio/webm;codecs=opus'
            });
            this.audioChunks = [];
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };
            this.mediaRecorder.onstop = () => {
                this.saveRecording();
            };
            this.mediaRecorder.start(100);
            this.isRecording = true;
            this.updateUI();
            this.startTimer();
        } catch (error) {
            console.error('Error starting recording:', error);
            alert('Could not start recording. Please check microphone permissions.');
        }
    }
    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.audioStream.getTracks().forEach(track => track.stop());
            this.isRecording = false;
            this.updateUI();
            this.stopTimer();
        }
    }
    saveRecording() {
        const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const url = URL.createObjectURL(blob);
        const timestamp = new Date().toLocaleString();
        const recording = {
            id: Date.now(),
            name: `Recording ${this.recordings.length + 1}`,
            url: url,
            blob: blob,
            timestamp: timestamp,
            duration: this.recordingDuration || '00:00',
            size: this.formatFileSize(blob.size)
        };
        this.recordings.push(recording);
        this.updateRecordingsList();
    }
    updateRecordingsList() {
        const container = document.getElementById('recordingsList');
        if (this.recordings.length === 0) {
            container.innerHTML = '<div class="no-recordings">No recordings yet</div>';
            return;
        }
        container.innerHTML = this.recordings.map(recording => `
            <div class="recording-item">
                <div class="recording-name">${recording.name}</div>
                <div class="recording-meta">
                    ${recording.timestamp} • ${recording.duration} • ${recording.size}
                </div>
                <div class="recording-controls">
                    <button class="audio-btn audio-btn-secondary" data-action="play" data-id="${recording.id}">
                        Play
                    </button>
                    <button class="audio-btn audio-btn-secondary" data-action="toggle-downloads" data-id="${recording.id}">
                        Download
                    </button>
                    <button class="audio-btn audio-btn-danger" data-action="delete" data-id="${recording.id}">
                        Delete
                    </button>
                </div>
                <div class="download-options" id="downloads-${recording.id}">
                    <button class="audio-btn audio-btn-secondary" data-action="download" data-id="${recording.id}" data-format="webm">
                        Download WebM
                    </button>
                    <button class="audio-btn audio-btn-secondary" data-action="download" data-id="${recording.id}" data-format="wav">
                        Download WAV
                    </button>
                </div>
            </div>
        `).join('');
        this.bindRecordingControls();
    }
    toggleDownloadOptions(id) {
        const options = document.getElementById(`downloads-${id}`);
        options.classList.toggle('active');
    }
    playRecording(id) {
        const recording = this.recordings.find(r => r.id === id);
        if (recording) {
            const audio = new Audio(recording.url);
            audio.play();
        }
    }
    async downloadRecording(id, format = 'webm') {
        const recording = this.recordings.find(r => r.id === id);
        if (!recording) return;
        let blob = recording.blob;
        let filename = `${recording.name}.${format}`;
        if (format === 'wav') {
            blob = await this.convertToWAV(recording.blob);
            filename = `${recording.name}.wav`;
        }
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
        URL.revokeObjectURL(a.href);
    }
    async convertToWAV(webmBlob) {
        return new Promise((resolve) => {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const fileReader = new FileReader();
            fileReader.onload = async (e) => {
                try {
                    const arrayBuffer = e.target.result;
                    const audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
                    const length = audioBuffer.length;
                    const numberOfChannels = audioBuffer.numberOfChannels;
                    const sampleRate = audioBuffer.sampleRate;
                    const wavBuffer = this.createWAVBuffer(audioBuffer, length, numberOfChannels, sampleRate);
                    const wavBlob = new Blob([wavBuffer], { type: 'audio/wav' });
                    resolve(wavBlob);
                } catch (error) {
                    console.error('Error converting to WAV:', error);
                    resolve(webmBlob);
                }
            };
            fileReader.readAsArrayBuffer(webmBlob);
        });
    }
    createWAVBuffer(audioBuffer, length, numberOfChannels, sampleRate) {
        const bytesPerSample = 2;
        const blockAlign = numberOfChannels * bytesPerSample;
        const byteRate = sampleRate * blockAlign;
        const dataSize = length * blockAlign;
        const buffer = new ArrayBuffer(44 + dataSize);
        const view = new DataView(buffer);
        const writeString = (offset, string) => {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        };
        writeString(0, 'RIFF');
        view.setUint32(4, 36 + dataSize, true);
        writeString(8, 'WAVE');
        writeString(12, 'fmt ');
        view.setUint32(16, 16, true);
        view.setUint16(20, 1, true);
        view.setUint16(22, numberOfChannels, true);
        view.setUint32(24, sampleRate, true);
        view.setUint32(28, byteRate, true);
        view.setUint16(32, blockAlign, true);
        view.setUint16(34, 16, true);
        writeString(36, 'data');
        view.setUint32(40, dataSize, true);
        let offset = 44;
        for (let i = 0; i < length; i++) {
            for (let channel = 0; channel < numberOfChannels; channel++) {
                const sample = Math.max(-1, Math.min(1, audioBuffer.getChannelData(channel)[i]));
                view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7FFF, true);
                offset += 2;
            }
        }
        return buffer;
    }
    deleteRecording(id) {
        const index = this.recordings.findIndex(r => r.id === id);
        if (index !== -1) {
            URL.revokeObjectURL(this.recordings[index].url);
            this.recordings.splice(index, 1);
            this.updateRecordingsList();
        }
    }
    clearRecordings() {
        if (confirm('Are you sure you want to delete all recordings?')) {
            this.recordings.forEach(recording => {
                URL.revokeObjectURL(recording.url);
            });
            this.recordings = [];
            this.updateRecordingsList();
        }
    }
    updateUI() {
        const indicator = document.getElementById('recordingIndicator');
        const startBtn = document.getElementById('startRecording');
        const stopBtn = document.getElementById('stopRecording');
        if (this.isRecording) {
            indicator.classList.add('active');
            startBtn.disabled = true;
            stopBtn.disabled = false;
        } else {
            indicator.classList.remove('active');
            startBtn.disabled = false;
            stopBtn.disabled = true;
        }
    }
    startTimer() {
        this.recordingStartTime = Date.now();
        this.timerInterval = setInterval(() => {
            const elapsed = Date.now() - this.recordingStartTime;
            const minutes = Math.floor(elapsed / 60000);
            const seconds = Math.floor((elapsed % 60000) / 1000);
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            document.getElementById('recordingTime').textContent = timeString;
            this.recordingDuration = timeString;
        }, 1000);
    }
    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
    }
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}
const audioCapture = new AudioCaptureModal();
window.AudioCaptureModal = AudioCaptureModal;
window.audioCapture = audioCapture;