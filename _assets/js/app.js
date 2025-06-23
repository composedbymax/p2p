(() => {
  const $ = (id) => document.getElementById(id),
    localVideo = $("localVideo"),
    remoteVideo = $("remoteVideo"),
    startBtn = $("startVideo"),
    stopBtn = $("stopVideo"),
    shareScreenBtn = $("shareScreen"),
    stopSharingBtn = $("stopSharing"),
    createBtn = $("createRoom"),
    joinBtn = $("joinRoom"),
    discBtn = $("disconnectBtn"),
    roomInput = $("roomId"),
    connStat = $("connectionStatus"),
    peerStat = $("peerStatus"),
    config = {
      iceServers: [
        { urls: "stun:stun.l.google.com:19302" },
        { urls: "stun:stun1.l.google.com:19302" },
      ],
    };
  let localStream,
    screenStream,
    pc,
    roomId,
    isCreator = false,
    pollAnswer,
    pollOffer,
    activeStream = null,
    isInActiveRoom = false,
    cameraStarted = false,
    encryptionKey = null,
    offerCreationTimeout = null,
    iceGatheringTimeout = null,
    maxOfferRetries = 3,
    currentOfferRetry = 0;

  if (roomInput) {
    roomId = roomInput.value;
  }
  const generateEncryptionKey = async (roomId) => {
    const encoder = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey(
      "raw",
      encoder.encode(roomId + "_encryption_salt"),
      { name: "PBKDF2" },
      false,
      ["deriveBits", "deriveKey"]
    );
    return await crypto.subtle.deriveKey(
      {
        name: "PBKDF2",
        salt: encoder.encode("webrtc_signal_salt"),
        iterations: 100000,
        hash: "SHA-256"
      },
      keyMaterial,
      { name: "AES-GCM", length: 256 },
      false,
      ["encrypt", "decrypt"]
    );
  };
  const encryptData = async (data, key) => {
    const encoder = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encodedData = encoder.encode(JSON.stringify(data));
    const encryptedData = await crypto.subtle.encrypt(
      {
        name: "AES-GCM",
        iv: iv
      },
      key,
      encodedData
    );
    const combined = new Uint8Array(iv.length + encryptedData.byteLength);
    combined.set(iv);
    combined.set(new Uint8Array(encryptedData), iv.length);
    return btoa(String.fromCharCode(...combined));
  };
  const decryptData = async (encryptedBase64, key) => {
    try {
      const combined = new Uint8Array(
        atob(encryptedBase64).split('').map(char => char.charCodeAt(0))
      );
      const iv = combined.slice(0, 12);
      const encryptedData = combined.slice(12);
      const decryptedData = await crypto.subtle.decrypt(
        {
          name: "AES-GCM",
          iv: iv
        },
        key,
        encryptedData
      );
      const decoder = new TextDecoder();
      return JSON.parse(decoder.decode(decryptedData));
    } catch (e) {
      console.error("Decryption failed:", e);
      return null;
    }
  };
  const updateStatus = (msg) => {
      if (connStat) {
        connStat.textContent = "Status: " + msg;
      }
      console.log(msg);
    },
    updatePeer = (msg) => {
      if (peerStat) {
        peerStat.textContent = msg;
      }
    },
    createBlackVideoTrack = () => {
      const canvas = document.createElement("canvas");
      canvas.width = 640;
      canvas.height = 480;
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = "black";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      return canvas.captureStream(10).getVideoTracks()[0];
    },
    startVideo = async () => {
      try {
        localStream = await navigator.mediaDevices.getUserMedia({
          video: true,
          audio: true,
        });
        localVideo.srcObject = localStream;
        activeStream = "camera";
        cameraStarted = true;
        if (pc) {
          const newTrack = localStream.getVideoTracks()[0];
          const sender = pc
            .getSenders()
            .find((s) => s.track && s.track.kind === "video");
          sender && sender.replaceTrack(newTrack);
        }
        startBtn.disabled = true;
        stopBtn.disabled = false;
        shareScreenBtn.disabled = false;
        stopSharingBtn.disabled = true;
        updateStatus(
          "Camera started. You can now create or join a connection."
        );
      } catch (e) {
        updateStatus("Failed to access camera/microphone: " + e.message);
      }
    },
    stopVideo = () => {
      if (activeStream === "camera" && localStream) {
        const blackTrack = createBlackVideoTrack();
        if (pc) {
          pc.getSenders().forEach((s) => {
            if (s.track && s.track.kind === "video") s.replaceTrack(blackTrack);
          });
        }
        localStream.getTracks().forEach((t) => t.stop());
        localVideo.srcObject = new MediaStream([blackTrack]);
        localStream = null;
        activeStream = null;
        cameraStarted = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        shareScreenBtn.disabled = true;
        stopSharingBtn.disabled = true;
        updateStatus("Camera stopped.");
      }
    },
    startScreenShare = async () => {
      try {
        screenStream = await navigator.mediaDevices.getDisplayMedia({
          video: { cursor: "always", displaySurface: "monitor" },
          audio: false,
        });
        screenStream.getVideoTracks()[0].onended = stopSharing;
        if (pc) {
          pc.getSenders()
            .filter((s) => s.track?.kind === "video")
            .forEach((s) => s.replaceTrack(screenStream.getVideoTracks()[0]));
        }
        localVideo.srcObject = screenStream;
        activeStream = "screen";
        shareScreenBtn.disabled = true;
        stopSharingBtn.disabled = false;
        if (localStream) {
          stopBtn.disabled = true;
        }
        updateStatus("Screen sharing started");
      } catch (e) {
        updateStatus("Failed to start screen sharing: " + e.message);
      }
    },
    stopSharing = () => {
      if (screenStream) {
        screenStream.getTracks().forEach((t) => t.stop());
        screenStream = null;
        if (localStream) {
          if (pc) {
            pc.getSenders()
              .filter((s) => s.track?.kind === "video")
              .forEach((s) => s.replaceTrack(localStream.getVideoTracks()[0]));
          }
          localVideo.srcObject = localStream;
          activeStream = "camera";
          stopBtn.disabled = false;
        } else {
          localVideo.srcObject = null;
          activeStream = null;
        }
        shareScreenBtn.disabled = !localStream;
        stopSharingBtn.disabled = true;
        updateStatus(
          "Screen sharing stopped" + (localStream ? ",returned to camera" : "")
        );
      }
    },
    clearAllTimeouts = () => {
      if (offerCreationTimeout) {
        clearTimeout(offerCreationTimeout);
        offerCreationTimeout = null;
      }
      if (iceGatheringTimeout) {
        clearTimeout(iceGatheringTimeout);
        iceGatheringTimeout = null;
      }
    },
    createOfferWithFailsafe = async () => {
      clearAllTimeouts();
      currentOfferRetry++;
      try {
        updateStatus(`Creating offer (attempt ${currentOfferRetry}/${maxOfferRetries})...`);
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        updateStatus("Offer created. Gathering ICE candidates...");
        iceGatheringTimeout = setTimeout(() => {
          console.warn("ICE gathering timeout - posting offer anyway");
          if (pc && pc.localDescription) {
            postSDP(pc.localDescription, "offer");
            updateStatus("Offer posted (ICE gathering timeout). Waiting for answer...");
          }
        }, 15000);
        offerCreationTimeout = setTimeout(async () => {
          console.error("Offer creation/posting timeout");
          if (currentOfferRetry < maxOfferRetries) {
            updateStatus(`Offer timeout. Retrying... (${currentOfferRetry + 1}/${maxOfferRetries})`);
            await recreatePeerConnection();
            createOfferWithFailsafe();
          } else {
            updateStatus("Failed to create offer after multiple attempts. Please try again.");
            resetConnectionState();
          }
        }, 30000);
      } catch (e) {
        console.error("Offer creation failed:", e);
        clearAllTimeouts();
        if (currentOfferRetry < maxOfferRetries) {
          updateStatus(`Offer creation failed. Retrying... (${currentOfferRetry + 1}/${maxOfferRetries})`);
          setTimeout(async () => {
            await recreatePeerConnection();
            createOfferWithFailsafe();
          }, 2000);
        } else {
          updateStatus("Failed to create offer after multiple attempts: " + e.message);
          resetConnectionState();
        }
      }
    },
    recreatePeerConnection = async () => {
      if (pc) {
        pc.close();
      }
      await new Promise(resolve => setTimeout(resolve, 1000));
      initPC();
    },
    resetConnectionState = () => {
      clearAllTimeouts();
      currentOfferRetry = 0;
      createBtn.disabled = joinBtn.disabled = false;
      discBtn.disabled = true;
      isInActiveRoom = false;
      if (pc) {
        pc.close();
        pc = null;
      }
    },
    initPC = () => {
      pc = new RTCPeerConnection(config);
      if (activeStream === "camera" && localStream) {
        localStream.getTracks().forEach((t) => pc.addTrack(t, localStream));
      } else if (activeStream === "screen" && screenStream) {
        pc.addTrack(screenStream.getVideoTracks()[0], screenStream);
        localStream?.getAudioTracks()[0] &&
          pc.addTrack(localStream.getAudioTracks()[0], localStream);
      }
      pc.onicecandidate = (e) => {
        if (e.candidate) {
          console.log("ICE candidate:", e.candidate);
        } else if (isCreator && pc.localDescription) {
          clearTimeout(iceGatheringTimeout);
          iceGatheringTimeout = null;
          console.log("ICE gathering complete, posting offer");
          postSDP(pc.localDescription, "offer");
          updateStatus("Offer posted. Waiting for answer...");
        }
      };
      pc.onicegatheringstatechange = () => {
        console.log("ICE gathering state:", pc.iceGatheringState);
        if (pc.iceGatheringState === "complete" && isCreator && pc.localDescription) {
          clearTimeout(iceGatheringTimeout);
          iceGatheringTimeout = null;
          console.log("ICE gathering complete (via state change), posting offer");
          postSDP(pc.localDescription, "offer");
          updateStatus("Offer posted. Waiting for answer...");
        }
      };
      pc.onconnectionstatechange = () => {
        const state = pc.connectionState;
        console.log("Connection state:", state);
        if (state === "connected") {
          clearAllTimeouts();
          currentOfferRetry = 0;
          updateStatus("Peer connection established!");
          updatePeer("Connected");
          deleteSDP();
        } else if (state === "disconnected" || state === "failed") {
          updateStatus("Peer connection lost.");
          updatePeer("Disconnected");
        }
      };
      pc.ontrack = (e) => {
        if (remoteVideo.srcObject !== e.streams[0]) {
          remoteVideo.srcObject = e.streams[0];
          updatePeer("Video connected");
        }
      };
    },
    deleteSDP = () => {
      ["offer", "answer"].forEach((r) => {
        fetch("index.php?roomId=" + roomId + "&role=" + r, { method: "DELETE" })
          .then((r) => r.json())
          .then((data) => console.log("Deleted " + r + ":", data))
          .catch((err) => console.error("Error deleting " + r, err));
      });
      updateStatus("P2P Connection established");
    },
    postSDP = async (sdp, role) => {
      try {
        const encryptedData = await encryptData(
          { type: sdp.type, sdp: sdp.sdp },
          encryptionKey
        );
        const response = await fetch("index.php?roomId=" + roomId + "&role=" + role, {
          method: "POST",
          headers: { "Content-Type": "text/plain" },
          body: encryptedData
        });
        const result = await response.json();
        console.log(role + " posted successfully:", result);
        if (role === "offer") {
          clearAllTimeouts();
          currentOfferRetry = 0;
        }
      } catch (e) {
        console.error("Error posting " + role, e);
        if (role === "offer" && isCreator) {
          if (currentOfferRetry < maxOfferRetries) {
            updateStatus(`Failed to post offer. Retrying... (${currentOfferRetry + 1}/${maxOfferRetries})`);
            setTimeout(async () => {
              await recreatePeerConnection();
              createOfferWithFailsafe();
            }, 2000);
          } else {
            updateStatus("Failed to post offer after multiple attempts.");
            resetConnectionState();
          }
        }
      }
    },
    createOffer = async () => {
      currentOfferRetry = 0;
      createOfferWithFailsafe();
    },
    createAnswer = async () => {
      try {
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        updateStatus("Answer created. Sending answer back to creator...");
      } catch (e) {
        updateStatus("Answer creation failed: " + e.message);
      }
    },
    pollSDP = (role, callback) => {
      const interval = setInterval(async () => {
        try {
          const res = await fetch(
            "index.php?roomId=" + roomId + "&role=" + role
          );
          const responseText = await res.text();
          
          if (responseText && responseText !== '{"status":"no data"}') {
            const decryptedData = await decryptData(responseText, encryptionKey);
            if (decryptedData && decryptedData.type && decryptedData.sdp) {
              clearInterval(interval);
              callback(decryptedData);
            }
          }
        } catch (e) {
          console.log("Waiting for " + role + "...");
        }
      }, 2000);
      return interval;
    },
    createRoom = async () => {
      if (!cameraStarted) {
        return updateStatus(
          "Please start your camera first"
        );
      }
      if (!roomId) {
        return updateStatus("Please enter or generate a room ID.");
      }
      try {
        encryptionKey = await generateEncryptionKey(roomId);
      } catch (e) {
        return updateStatus("Failed to generate encryption key: " + e.message);
      }
      isCreator = true;
      initPC();
      isInActiveRoom = true;
      currentOfferRetry = 0;
      updateStatus("Connection initiated! Creating offer...");
      createBtn.disabled = joinBtn.disabled = true;
      discBtn.disabled = false;
      createOffer();
      pollAnswer = pollSDP("answer", async (data) => {
        clearAllTimeouts();
        updateStatus("Answer received. Establishing connection...");
        await pc.setRemoteDescription(new RTCSessionDescription(data));
      });
    },
    joinRoom = async () => {
      if (!cameraStarted) {
        return updateStatus(
          "Please start your camera first"
        );
      }
      if (!roomId) {
        return updateStatus("Please enter a room ID to join.");
      }
      try {
        encryptionKey = await generateEncryptionKey(roomId);
      } catch (e) {
        return updateStatus("Failed to generate encryption key: " + e.message);
      }
      isCreator = false;
      initPC();
      isInActiveRoom = true;
      updateStatus("Joining connection. Waiting for offer...");
      createBtn.disabled = joinBtn.disabled = true;
      discBtn.disabled = false;
      pollOffer = pollSDP("offer", async (data) => {
        updateStatus("Offer received. Creating answer...");
        await pc.setRemoteDescription(new RTCSessionDescription(data));
        await createAnswer();
        pc.onicecandidate = (e) => {
          if (!e.candidate && pc.localDescription) {
            postSDP(pc.localDescription, "answer");
          }
        };
      });
    },
    disconnect = () => {
      [pollAnswer, pollOffer].forEach((i) => i && clearInterval(i));
      clearAllTimeouts();
      currentOfferRetry = 0;
      deleteSDP();
      if (pc) {
        pc.close();
        pc = null;
      }
      remoteVideo.srcObject = null;
      createBtn.disabled = joinBtn.disabled = false;
      discBtn.disabled = true;
      isInActiveRoom = false;
      encryptionKey = null;
      updateStatus("Disconnected from peer.");
      updatePeer("Waiting for peer to connect...");
    };
  window.addEventListener("load", () => {
    if (
      !startBtn ||
      !stopBtn ||
      !shareScreenBtn ||
      !stopSharingBtn ||
      !createBtn ||
      !joinBtn ||
      !discBtn
    ) {
      return;
    }
    startBtn.addEventListener("click", startVideo);
    stopBtn.addEventListener("click", stopVideo);
    shareScreenBtn.addEventListener("click", startScreenShare);
    stopSharingBtn.addEventListener("click", stopSharing);
    createBtn.addEventListener("click", createRoom);
    joinBtn.addEventListener("click", joinRoom);
    discBtn.addEventListener("click", disconnect);
    const localVideoContainer = document.querySelector(
      ".video-container:nth-child(1)"
    );
    const remoteVideoContainer = document.querySelector(
      ".video-container:nth-child(2)"
    );
    if (localVideoContainer && remoteVideoContainer) {
      const createExpandBtn = (container) => {
        const expandBtn = document.createElement("button");
        expandBtn.className = "expand-btn";
        const expandIcon = document.createElement("span");
        expandIcon.className = "expand-icon";
        expandBtn.appendChild(expandIcon);
        expandBtn.addEventListener("click", () => {
          const isExpanded = container.classList.contains("expanded");
          document.querySelectorAll(".video-container").forEach((cont) => {
            cont.classList.remove("expanded");
            const btn = cont.querySelector(".expand-btn");
            if (btn) {
              btn.innerHTML = "";
              const icon = document.createElement("span");
              icon.className = "expand-icon";
              btn.appendChild(icon);
            }
          });
          if (!isExpanded) {
            container.classList.add("expanded");
            expandBtn.innerHTML = "";
            const retractIcon = document.createElement("span");
            retractIcon.className = "retract-icon";
            expandBtn.appendChild(retractIcon);
          }
        });
        container.appendChild(expandBtn);
      };
      createExpandBtn(localVideoContainer);
      createExpandBtn(remoteVideoContainer);
    }
  });
  window.addEventListener("beforeunload", (e) => {
    if (isInActiveRoom) {
      e.preventDefault();
      e.returnValue =
        "You are currently in an active video call. Are you sure you want to leave?";
      return e.returnValue;
    }
  });
})();
document.getElementById("deleteRoomBtn").addEventListener("click", async () => {
  if (confirm("Delete room?")) {
    const id = document.getElementById("roomId").value;
    try {
      const res = await fetch(
        `api/delete_room.php?roomId=${encodeURIComponent(id)}`,
        { method: "DELETE" }
      );
      const d = await res.json();
      if (d.status === "deleted") {
        alert("Room deleted successfully.");
        location.href = "index.php";
      } else {
        alert("Failed: " + d.message);
      }
    } catch (e) {
      console.error(e);
      alert("Error deleting room.");
    }
  }
});