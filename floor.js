document.addEventListener('DOMContentLoaded', () => {
    const generateRoomBtn = document.getElementById('generateRoomBtn');
    const roomIdInput = document.getElementById('roomIdInput');
    if (generateRoomBtn && roomIdInput) {
        generateRoomBtn.addEventListener('click', () => {
            const roomId = Array.from({ length: 12 }, () => 
                "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789".charAt(
                    Math.floor(Math.random() * 62)
                )
            ).join('');
            roomIdInput.value = roomId;
        });
    }
});