function injectTranscriptMergerUI() {
    const style = document.createElement('style');
    style.innerHTML = `
        #popoutContainer{position: fixed;top: 0;left: 0;width: 320px;height: 100vh;background: var(--glass);backdrop-filter:blur(20px);-webkit-backdrop-filter: blur(20px);box-shadow: 2px 0 5px var(--shade2);transform: translateX(-100%);transition: transform 0.3s ease;z-index: 999;display: flex;flex-direction: column;border-right:2px solid var(--shade2);}
        #popoutContainer.active{transform: translateX(0);}
        #popoutContainer .tab{position: absolute;top: 0;width: 50px;height: 50px;right: -50px;background: var(--glass);backdrop-filter:blur(20px);-webkit-backdrop-filter: blur(20px);color: var(--white);padding: 10px;text-align: center;cursor: pointer;border-radius: 0 4px 4px 0;font-weight: 600;font-size: 16px;border:2px solid var(--shade2);}
        #popoutContainer .tab:hover{background: var(--blueh);color:#000;}
        #popoutContainer .content{padding: 10px;overflow-y: auto;flex-grow: 1;background: var(--shade);color: var(--white);}
        .content{margin-top:2rem;}
        #popoutContainer input, #popoutContainer textarea, #popoutContainer select{width: 100%;padding: 10px;border-radius: 6px;border: 1px solid #444;background: var(--shade2);color: #fff;font-size: 14px;margin-bottom: 12px;}
        #popoutContainer button{padding: 10px 14px;background: var(--blue);color: #000;font-weight: 600;border: none;border-radius: 6px;width: 100%;cursor: pointer;margin-bottom: 8px;transition: background 0.3s;}
        #popoutContainer button:hover{background: var(--blueh);}
        #popoutContainer button:disabled{background: #444;cursor: not-allowed;}
        .transcript-line{margin-bottom: 8px;font-size: 0.9em;color: var(--white);}
        .timestamp{font-weight: bold;margin-right: 5px;}
        .speaker{margin-right: 5px;color: var(--blue);}
        h3{padding: 1rem;text-align: center;}
        #outputContent{border: 1px solid #555;padding: 10px;background: var(--shade1);color: #fff;height: 150px;overflow-y: auto;margin-bottom: 12px;}
    `;
    document.head.appendChild(style);
    const container = document.createElement('div');
    container.id = 'popoutContainer';
    container.innerHTML = `
        <div class="tab">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 17l5-5-5-5M5 12h14"></path>
            </svg>
        </div>
        <div class="content">
            <h3>Merge Transcripts</h3>
            <input type="text" id="speaker1" placeholder="Speaker 1 Name" />
            <textarea id="transcript1" rows="5" placeholder="Transcript 1"></textarea>
            <input type="text" id="speaker2" placeholder="Speaker 2 Name" />
            <textarea id="transcript2" rows="5" placeholder="Transcript 2"></textarea>
            <button id="mergeBtn">Merge Transcripts</button>
            <div id="outputContent"></div>
            <select id="exportFormat">
                <option value="csv">CSV</option>
                <option value="json">JSON</option>
                <option value="txt">TXT</option>
            </select>
            <button id="downloadBtn" disabled>Download</button>
            <button id="createEndpointBtn" disabled>Create Conversation Endpoint</button>
            <button id="deleteEndpointBtn" disabled>Delete Conversation Endpoint</button>
        </div>
    `;
    document.body.appendChild(container);
    function togglePanel() {
        container.classList.toggle('active');
    }
    container.querySelector('.tab').addEventListener('click', togglePanel);
    document.addEventListener('click', function(event) {
        if (!container.contains(event.target) && container.classList.contains('active')) {
            container.classList.remove('active');
        }
    });
    let mergedLines = [];
    function mergeTranscripts() {
        const speaker1Name = document.getElementById('speaker1').value.trim() || 'Speaker 1';
        const speaker2Name = document.getElementById('speaker2').value.trim() || 'Speaker 2';
        const transcript1 = document.getElementById('transcript1').value.trim();
        const transcript2 = document.getElementById('transcript2').value.trim();
        const lines1 = parseTranscript(transcript1, speaker1Name);
        const lines2 = parseTranscript(transcript2, speaker2Name);
        mergedLines = [...lines1, ...lines2].sort((a, b) => a.timestamp - b.timestamp);
        const outputContent = document.getElementById('outputContent');
        const downloadBtn = document.getElementById('downloadBtn');
        const createEndpointBtn = document.getElementById('createEndpointBtn');
        const deleteEndpointBtn = document.getElementById('deleteEndpointBtn');
        if (mergedLines.length) {
            outputContent.innerHTML = mergedLines.map(line => `
                <div class="transcript-line">
                    <span class="timestamp">${line.timestampFormatted}</span>
                    <span class="speaker">${line.speaker}:</span>
                    <span class="text">${line.text}</span>
                </div>
            `).join('');
            downloadBtn.disabled = false;
            createEndpointBtn.disabled = false;
            deleteEndpointBtn.disabled = false;
        } else {
            outputContent.innerHTML = '<p>Please provide valid transcripts.</p>';
            downloadBtn.disabled = true;
            createEndpointBtn.disabled = true;
            deleteEndpointBtn.disabled = true;
        }
    }
    function downloadFile() {
        if (!mergedLines.length) return;
        const format = document.getElementById('exportFormat').value;
        let content = '';
        let mimeType = '';
        let fileName = '';
        if (format === 'csv') {
            content = [
                ['Timestamp', 'Speaker', 'Text'],
                ...mergedLines.map(line => [
                    line.timestampFormatted,
                    line.speaker,
                    line.text
                ])
            ].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            mimeType = 'text/csv;charset=utf-8;';
            fileName = 'transcript.csv';
        } else if (format === 'json') {
            content = JSON.stringify(mergedLines, null, 2);
            mimeType = 'application/json;charset=utf-8;';
            fileName = 'transcript.json';
        } else if (format === 'txt') {
            content = mergedLines.map(line => {
                return `[${line.timestampFormatted}] ${line.speaker}: ${line.text}`;
            }).join('\n');
            mimeType = 'text/plain;charset=utf-8;';
            fileName = 'transcript.txt';
        }
        const blob = new Blob([content], { type: mimeType });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', fileName);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    function createEndpoint() {
        if (!mergedLines.length) {
            alert('Please merge transcripts first.');
            return;
        }
        fetch('api/transcript_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                action: 'create',
                transcripts: mergedLines 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.url) {
                const outputContent = document.getElementById('outputContent');
                outputContent.innerHTML += `<p><strong>Endpoint URL:</strong> <a href="${data.url}" target="_blank">${data.url}</a></p>`;
            } else {
                alert('Error: ' + (data.error || 'No URL returned.'));
            }
        })
        .catch(error => {
            alert('Error contacting endpoint: ' + error);
        });
    }
    function deleteEndpoint() {
        fetch('transcript_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                action: 'delete'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const outputContent = document.getElementById('outputContent');
                outputContent.innerHTML += `<p>Conversation endpoint deleted successfully.</p>`;
            } else {
                alert('Error: ' + (data.error || 'Could not delete endpoint.'));
            }
        })
        .catch(error => {
            alert('Error contacting deletion endpoint: ' + error);
        });
    }
    function parseTranscript(transcript, speaker) {
        if (!transcript) return [];
        const regex = /\[(?:\d{1,2}\/\d{1,2}\/\d{4},\s*)?(\d{1,2}:\d{2}:\d{2} [APM]{2})\]\s*([^\[]+)/g;
        const lines = [];
        let match;
        while ((match = regex.exec(transcript)) !== null) {
            const timestampStr = match[1];
            const text = match[2].trim();
            const normalizedTimestamp = normalizeTimestamp(timestampStr);
            const [time, period] = normalizedTimestamp.split(' ');
            const [hours, minutes, seconds] = time.split(':');
            let hour = parseInt(hours);
            if (period === 'PM' && hour !== 12) {
                hour += 12;
            } else if (period === 'AM' && hour === 12) {
                hour = 0;
            }
            const timestamp = new Date(1970, 0, 1, hour, parseInt(minutes), parseInt(seconds));
            lines.push({
                timestamp,
                timestampFormatted: normalizedTimestamp,
                speaker,
                text
            });
        }
        return lines;
    }
    function normalizeTimestamp(timestampStr) {
        const [time, period] = timestampStr.split(' ');
        const [hours, minutes, seconds] = time.split(':');
        const paddedHours = hours.padStart(2, '0');
        return `${paddedHours}:${minutes}:${seconds} ${period}`;
    }
    document.getElementById('mergeBtn').addEventListener('click', mergeTranscripts);
    document.getElementById('downloadBtn').addEventListener('click', downloadFile);
    document.getElementById('createEndpointBtn').addEventListener('click', createEndpoint);
    document.getElementById('deleteEndpointBtn').addEventListener('click', deleteEndpoint);
}
injectTranscriptMergerUI();