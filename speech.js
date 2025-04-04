(function () {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        console.warn('Speech Recognition API not supported in this browser.');
        return;
    }
    const recognition = new SpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = 'en-US';
    let stream = null;
    let finalTranscript = '';
    let interimTranscript = '';
    let lastTimestamp = 0;
    let showTimestamps = false;
    const style = document.createElement('style');
    style.textContent = `
        .sbtn {
            background: rgba(0, 251, 255, 0.9);
            color: #000;
            font-weight: 600;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
            margin: 4px;
            width: 100%;
            text-align: center;
            display: inline-block;
        }
        .sbtn:hover { background: rgba(0, 251, 255, 1); transform: scale(1.05); }
        .sbtn:disabled { background: #444; cursor: not-allowed; }
        #speech-recorder-container {
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            width: 450px;
            max-width: 90%;
            background: rgba(0, 0, 0, 0.95);
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            transition: transform 0.3s ease;
            transform: translateX(calc(100% - 0px));
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: -4px 0 10px rgba(0,0,0,0.2);
        }
        #speech-recorder-container.expanded { transform: translateX(0); }
        #microphone-tab {
            position: absolute;
            left: 0px;
            top: 10%;
            transform: rotate(-90deg) translateY(-50%);
            transform-origin: left center;
            background: rgba(0, 0, 0, 0.95);
            padding: 10px 15px;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            box-shadow: -4px 0 10px rgba(0,0,0,0.2);
        }
        #microphone-tab:hover { background: rgba(0, 251, 255, 1); color:#000; }
        #microphone-tab svg { transform: rotate(90deg); }
        #speech-recorder-content {
            margin-top: 2rem;
            flex-grow: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        #speech-status { margin: 10px 0; font-weight: bold; }
        #speech-transcript {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #555;
            border-radius: 6px;
            padding: 10px;
            color: #fff;
            resize: vertical;
            flex-grow: 1;
            margin-bottom: 10px;
        }
        .control-group { margin: 10px 0; }
        .control-group label { display: block; margin-bottom: 5px; font-size: 14px; }
        #frequency-control-group { display: none; }
        @media (max-width: 600px) {
            #speech-recorder-container { width: 80%; transition: 0.5s; }
        }
    `;
    document.head.appendChild(style);
    const container = document.createElement('div');
    container.id = 'speech-recorder-container';
    container.innerHTML = `
        <div id="microphone-tab">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"></path>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                <line x1="12" x2="12" y1="19" y2="22"></line>
            </svg>
        </div>
        <div id="speech-recorder-content">
            <div id="speech-status">Status: Idle</div>
            <div id="frequency-control-group" class="control-group">
                <label for="update-frequency">Timestamp Update Interval: <span id="frequency-value">0s</span></label>
                <input type="range" id="update-frequency" min="0" max="20" value="0" step="0.1">
            </div>
            <textarea id="speech-transcript" rows="10" placeholder="Speech transcript will appear here..."></textarea>
            <div>
                <button id="start-sbtn" class="sbtn">Start Recording</button>
                <button id="stop-sbtn" class="sbtn" disabled>Stop Recording</button>
                <button id="export-sbtn" class="sbtn">Export to TXT</button>
                <button id="copy-sbtn" class="sbtn">Copy to Clipboard</button>
                <button id="toggle-timestamps" class="sbtn">Enable Timestamps</button>
            </div>
        </div>
    `;
    document.body.appendChild(container);
    const statusDiv = document.getElementById('speech-status');
    const transcriptArea = document.getElementById('speech-transcript');
    const startBtn = document.getElementById('start-sbtn');
    const stopBtn = document.getElementById('stop-sbtn');
    const exportBtn = document.getElementById('export-sbtn');
    const copyBtn = document.getElementById('copy-sbtn');
    const toggleTimestampBtn = document.getElementById('toggle-timestamps');
    const microphoneTab = document.getElementById('microphone-tab');
    const updateFrequency = document.getElementById('update-frequency');
    const frequencyValue = document.getElementById('frequency-value');
    const frequencyGroup = document.getElementById('frequency-control-group');
    let isExpanded = false;
    microphoneTab.addEventListener('click', () => {
        isExpanded = !isExpanded;
        container.classList.toggle('expanded', isExpanded);
    });
    updateFrequency.addEventListener('input', (e) => {
        const delayInSeconds = parseFloat(e.target.value);
        frequencyValue.textContent = `${delayInSeconds}s`;
    });
    startBtn.addEventListener('click', () => {
        navigator.mediaDevices.getUserMedia({ audio: true })
        .then(mediaStream => {
            stream = mediaStream;
            recognition.start();
            statusDiv.textContent = 'Status: Recording...';
            startBtn.disabled = true;
            stopBtn.disabled = false;
            finalTranscript = '';
            interimTranscript = '';
            lastTimestamp = 0;
        })
        .catch(err => {
            console.error('Error accessing microphone:', err);
            statusDiv.textContent = `Microphone access error: ${err.message}`;
        });
    });
    stopBtn.addEventListener('click', () => {
        recognition.stop();
        statusDiv.textContent = 'Status: Stopped';
        startBtn.disabled = false;
        stopBtn.disabled = true;
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });
    recognition.addEventListener('error', (event) => {
        console.error('Speech Recognition Error:', event.error);
        switch(event.error) {
            case 'no-speech':
                statusDiv.textContent = 'No speech detected. Check your microphone.';
                break;
            case 'audio-capture':
                statusDiv.textContent = 'No microphone found. Ensure device is connected.';
                break;
            case 'not-allowed':
                statusDiv.textContent = 'Microphone permission denied. Please enable in browser settings.';
                break;
            default:
                statusDiv.textContent = `Recording error: ${event.error}`;
        }
        startBtn.disabled = false;
        stopBtn.disabled = true;
    });
    recognition.addEventListener('result', (event) => {
        let latestInterim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const result = event.results[i];
            const transcript = result[0].transcript;
            if (result.isFinal) {
                if (showTimestamps) {
                    const currentDelay = parseFloat(updateFrequency.value) * 1000;
                    const now = Date.now();
                    if (currentDelay === 0 || now - lastTimestamp >= currentDelay) {
                        lastTimestamp = now;
                        const timestamp = new Date().toLocaleString();
                        finalTranscript += `[${timestamp}] ${transcript}\n`;
                    } else {
                        finalTranscript += transcript + '\n';
                    }
                } else {
                    finalTranscript += transcript + '\n';
                }
            } else {
                latestInterim = transcript;
            }
        }
        interimTranscript = latestInterim;
        transcriptArea.value = finalTranscript + interimTranscript;
        transcriptArea.scrollTop = transcriptArea.scrollHeight;
    });
    recognition.addEventListener('end', () => {
        if (startBtn.disabled) {
            recognition.start();
        }
    });
    exportBtn.addEventListener('click', () => {
        const blob = new Blob([transcriptArea.value], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'transcript.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });
    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(transcriptArea.value);
            statusDiv.textContent = 'Transcript copied to clipboard!';
        } catch (err) {
            console.error('Clipboard copy failed:', err);
            statusDiv.textContent = 'Clipboard copy failed!';
        }
    });
    toggleTimestampBtn.addEventListener('click', () => {
        showTimestamps = !showTimestamps;
        toggleTimestampBtn.textContent = showTimestamps ? 'Disable Timestamps' : 'Enable Timestamps';
        frequencyGroup.style.display = showTimestamps ? 'block' : 'none';
    });
    function logRecognitionDetails() {
        console.log('Browser Speech Recognition Support:', !!SpeechRecognition);
        console.log('Continuous Mode:', recognition.continuous);
        console.log('Interim Results:', recognition.interimResults);
        console.log('Language:', recognition.lang);
    }
    logRecognitionDetails();
})();