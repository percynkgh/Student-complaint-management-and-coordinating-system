const express = require("express");
const http = require("http");
const socketIo = require("socket.io");

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"],
  },
});

const rooms = {};

io.on("connection", (socket) => {
  console.log("New client connected:", socket.id);

  socket.on("join-room", (roomId, userId, userName, userRole) => {
    socket.join(roomId);

    if (!rooms[roomId]) {
      rooms[roomId] = [];
    }

    rooms[roomId].push({
      socketId: socket.id,
      userId,
      userName,
      userRole,
    });

    // Notify others in the room
    socket.to(roomId).emit("user-joined", { userId, userName, userRole });

    console.log(`User ${userName} joined room ${roomId}`);
  });

  socket.on("leave-room", (roomId, userId) => {
    socket.leave(roomId);

    if (rooms[roomId]) {
      rooms[roomId] = rooms[roomId].filter((u) => u.socketId !== socket.id);

      if (rooms[roomId].length === 0) {
        delete rooms[roomId];
      }
    }

    socket.to(roomId).emit("user-left", { userId });
    console.log(`User ${userId} left room ${roomId}`);
  });

  socket.on("offer", (data) => {
    socket.to(data.room).emit("offer", {
      from: data.from,
      offer: data.offer,
    });
  });

  socket.on("answer", (data) => {
    socket.to(data.room).emit("answer", {
      from: data.from,
      answer: data.answer,
    });
  });

  socket.on("ice-candidate", (data) => {
    socket.to(data.room).emit("ice-candidate", {
      from: data.from,
      candidate: data.candidate,
    });
  });

  socket.on("media-status", (roomId, userId, type, enabled) => {
    socket.to(roomId).emit("media-status", { userId, type, enabled });
  });

  socket.on("chat-message", (roomId, userId, userName, message) => {
    io.to(roomId).emit("chat-message", { userId, userName, message });
  });

  socket.on("disconnect", () => {
    // Find and remove user from all rooms
    Object.keys(rooms).forEach((roomId) => {
      const user = rooms[roomId]?.find((u) => u.socketId === socket.id);
      if (user) {
        socket.to(roomId).emit("user-left", { userId: user.userId });
        rooms[roomId] = rooms[roomId].filter((u) => u.socketId !== socket.id);

        if (rooms[roomId].length === 0) {
          delete rooms[roomId];
        }
      }
    });

    console.log("Client disconnected:", socket.id);
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`Signaling server running on port ${PORT}`);
});
