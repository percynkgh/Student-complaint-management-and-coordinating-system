document.addEventListener("DOMContentLoaded", function () {
  // Animate statistics counters
  animateStatsCounters();

  // Initialize Socket.IO for real-time message notifications
  initRealTimeMessaging();

  console.log("Classrep Dashboard JS loaded");
});

// REAL-TIME MESSAGING WITH SOCKET.IO

let messagingSocket = null;
let currentUserId = parseInt(document.body.dataset.userId);

function initRealTimeMessaging() {
  if (messagingSocket && messagingSocket.connected) return;

  messagingSocket = io("http://localhost:3000", { transports: ["websocket"] });

  messagingSocket.on("connect", () => {
    console.log("✅ Dashboard socket connected");
    messagingSocket.emit("register", currentUserId);
    messagingSocket.emit("join_direct_messages", currentUserId);
  });

  messagingSocket.on("connect_error", (err) => {
    console.error("❌ Socket error:", err);
  });

  // Listen for new direct messages
  messagingSocket.on("direct_message", (data) => {
    console.log("📩 New message received on dashboard:", data);

    if (data.receiver_id == currentUserId) {
      // Reload page to update badge count
      location.reload();
    }
  });
}

// STATS COUNTER ANIMATION

function animateStatsCounters() {
  const statNumbers = document.querySelectorAll(".stat-number");

  statNumbers.forEach((stat) => {
    const targetText = stat.innerText;
    const target = parseInt(targetText);

    if (isNaN(target)) return;

    let current = 0;
    const increment = Math.ceil(target / 50);
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) {
        stat.innerText = target;
        clearInterval(timer);
      } else {
        stat.innerText = Math.floor(current);
      }
    }, 20);
  });
}

// CLEANUP ON PAGE UNLOAD

window.addEventListener("beforeunload", function () {
  if (messagingSocket) {
    messagingSocket.disconnect();
  }
});
