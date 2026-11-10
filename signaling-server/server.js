const express = require('express');
const http = require('http');
const socketIo = require('socket.io');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

const userSockets = new Map();

io.on('connection', (socket) => {
    console.log('New client connected:', socket.id);

    socket.on('register', (userId) => {
        userSockets.set(userId, socket.id);
        socket.userId = userId;
        console.log(`User ${userId} registered with socket ${socket.id}`);
    });

    socket.on('join-room', (roomId, userId, userName, userRole) => {
        socket.join(roomId);
        console.log(`${userName} (${userId}) joined room ${roomId}`);
        socket.to(roomId).emit('user-joined', { userId, userName, userRole });
    });

    socket.on('offer', ({ to, offer, room }) => {
        const targetSocketId = userSockets.get(to);
        if (targetSocketId) {
            console.log(`Offer from ${socket.userId} to ${to} (${targetSocketId})`);
            io.to(targetSocketId).emit('offer', { from: socket.userId, offer });
        } else {
            console.log(`Target user ${to} not found`);
        }
    });

    socket.on('answer', ({ to, answer, room }) => {
        const targetSocketId = userSockets.get(to);
        if (targetSocketId) {
            console.log(`Answer from ${socket.userId} to ${to}`);
            io.to(targetSocketId).emit('answer', { from: socket.userId, answer });
        }
    });

    socket.on('ice-candidate', ({ to, candidate, room }) => {
        const targetSocketId = userSockets.get(to);
        if (targetSocketId) {
            console.log(`ICE from ${socket.userId} to ${to}`);
            io.to(targetSocketId).emit('ice-candidate', { from: socket.userId, candidate });
        }
    });

    socket.on('leave-room', (roomId, userId) => {
        socket.to(roomId).emit('user-left', { userId });
        socket.leave(roomId);
        console.log(`User ${userId} left room ${roomId}`);
    });

    socket.on('disconnect', () => {
        if (socket.userId) {
            userSockets.delete(socket.userId);
            console.log(`User ${socket.userId} disconnected`);
            // Notify all rooms the user was in
            const rooms = Array.from(socket.rooms);
            rooms.forEach(room => {
                if (room !== socket.id) {
                    socket.to(room).emit('user-left', { userId: socket.userId });
                }
            });
        }
        console.log('Client disconnected:', socket.id);
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`Signaling server running on port ${PORT}`);
});