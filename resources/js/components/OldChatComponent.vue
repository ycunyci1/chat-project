<template>
    <div class="chat-app">
        <!-- Боковая панель для списка чатов -->
        <aside class="sidebar">
            <ul class="chat-list">
                <li v-for="chat in chats" :key="chat.id" @click="openChat(chat.id)" class="chat-item">
                    <img class="chat-item-avatar" :src="chat.avatar" alt="Avatar">
                    <div class="chat-item-details">
                        <h5 class="chat-item-name">{{ chat.companion_name }}</h5>
                        <p class="chat-item-last-message">{{
                                chat.sender + ': ' + chat.last_message
                            }}</p>
                    </div>
                    <span class="chat-item-timestamp">{{ chat.last_message_timestamp }}</span>
                </li>
            </ul>
        </aside>

        <!-- Основная область для деталей чата и отправки сообщений -->
        <section class="chat-main" v-if="currentChat">
            <!-- Заголовок текущего чата -->
            <header class="chat-header">
                <h2 class="chat-companion-name">{{ currentChat.companion.name }}</h2>
                <div>{{ currentChat.companion.is_online ? 'В сети' : 'Не в сети' }}</div>
                <div v-if="!currentChat.companion.is_online">Последняя активность:
                    {{ currentChat.companion.last_seen_at }}
                </div>
                <div v-if="otherUserIsTyping">Собеседник печатает...</div>
            </header>
            <!-- Область сообщений -->
            <div class="chat-messages" ref="chatMessages">
                <div v-for="message in currentChat.messages" :key="message.id"
                     :class="{'message': true, 'my-message': message.user.id === this.userId, 'their-message': message.user.id !== this.userId}">
                    <img :src="message.user.avatar" class="message-avatar"> <!-- Элемент для аватара отправителя -->
                    <div class="message-content">{{ message.text }}</div>
                    <div class="message-time">{{ message.timestamp }}</div>
                </div>
            </div>
            <!-- Поле ввода нового сообщения -->
            <footer class="chat-input-container">
                <input type="text" @input="handleInput" placeholder="Введите сообщение..." class="chat-input"
                       v-model="newMessage" @keyup.enter="sendMessage">
                <button class="send-button" @click="sendMessage">Отправить</button>
            </footer>
        </section>
    </div>
</template>

<script>
import axios from 'axios';
import _ from 'lodash';

export default {
    data() {
        return {
            chats: [],
            currentChat: null,
            userId: 1,
            newMessage: '',
            otherUserIsTyping: null,
            notifyTyping: _.debounce(this.sendTypingNotification, 1000)
        };
    },
    created() {
        this.fetchUserId();
    },
    mounted() {
        this.listenForChatUpdates();
    },
    methods: {
        listenForTyping() {
            Echo.private(`chat.${this.currentChat.chat_id}`)
                .listen('TypingEvent', (e) => {
                    if (e.userId !== this.userId) {
                        this.otherUserIsTyping = e.typing;
                    }
                });
        },
        sendTypingNotification() {
            axios.post('/api/typing', {
                chatId: this.currentChat.chat_id,
                typing: true
            }, {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            });
            clearTimeout(this.typingTimer);
            this.typingTimer = setTimeout(() => {
                axios.post('/api/typing', {chatId: this.currentChat.chat_id, typing: false}, {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('access_token')}`
                    }
                });
            }, 3000);
        },
        handleInput() {
            this.notifyTyping();
        },
        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.chatMessages;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
        sendMessage() {
            if (!this.newMessage.trim()) return; // Проверка на пустое сообщение
            const message = {
                text: this.newMessage,
            };
            axios.post(`/api/chats/${this.currentChat.chat_id}/messages`, message, {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then(response => {
                    this.newMessage = ''; // Очищаем поле ввода
                })
                .catch(error => {
                    console.error('Ошибка при отправке сообщения:', error);
                });
        },
        fetchUserId() {
            axios.get('/api/get-user-id')
                .then((response) => {
                    this.userId = response.data.userId;
                    localStorage.setItem('access_token', response.data.access_token)
                    this.fetchChats();
                })
                .catch((error) => {
                    console.error('Ошибка при получении userId:', error);
                });
        },
        listenForChatMessages(chatId) {
            Echo.private(`user-${chatId}-messages`)
                .listen('MessageSent', (e) => {
                    this.currentChat.messages.push(e.message)
                    this.scrollToBottom();
                });
        },
        updateUserStatus(isOnline, lastSeen) {
            this.currentChat.companion.is_online = isOnline
            this.currentChat.companion.last_seen_at = lastSeen
        },
        listenForChangeUserStatus(userId) {
            Echo.channel(`user-${userId}-status`)
                .listen('UserStatusUpdatedEvent', (event) => {
                    this.updateUserStatus(event.isOnline, event.lastSeen);
                });
        },
        openChat(chatId) {
            axios.get(`/api/chats/${chatId}/messages`, {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then(response => {
                    if (this.currentChat) {
                        Echo.leave(`user-${this.currentChat.chat_id}-messages`)
                    }
                    this.currentChat = response.data;
                    this.scrollToBottom();
                    this.listenForChatMessages(this.currentChat.chat_id)
                    this.listenForChangeUserStatus(this.currentChat.companion.id)
                    this.listenForTyping();
                })
                .catch(error => {
                    console.error('Ошибка при загрузке чата:', error);
                });
        },
        fetchChats() {
            axios.get('/api/chats', {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then(response => {
                    this.chats = response.data;
                })
                .catch(error => {
                    console.error('Ошибка при загрузке чатов:', error);
                });
        },
        listenForChatUpdates() {
            if (!this.userId) this.userId = 1; // Убедитесь, что userId установлен
            Echo.private(`user-${this.userId}-chats`)
                .listen('ChatsUpdated', (response) => {
                    this.chats = response.chats
                });
        },
    }
}

</script>

<style scoped>
.chat-app {
    display: flex;
    height: 100vh; /* Полная высота экрана */
    background-color: #fff; /* Фон всего приложения чата */
}

.sidebar {
    max-width: 30%; /* Ширина боковой панели */
    min-width: 30%; /* Ширина боковой панели */
    background-color: #f7f7f7; /* Фон боковой панели */
    overflow-y: auto; /* Добавляет прокрутку при переполнении */
    border-right: 1px solid #e0e0e0; /* Разделяющая линия */
}

.chat-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.chat-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    cursor: pointer;
}

.chat-item-avatar {
    width: 50px; /* Размер аватара */
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    object-fit: cover; /* Обрезает изображение, чтобы оно заполняло элемент */
}

.chat-item-details {
    flex-grow: 1;
}

.chat-item-name {
    font-size: 1rem;
    margin: 0;
}

.chat-item-last-message {
    font-size: 0.875rem;
    color: #666;
    margin: 5px 0 0;
}

.chat-item-timestamp {
    font-size: 0.75rem;
    color: #999;
}

.chat-main {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    max-width: 70%;
}

.chat-header {
    padding: 20px;
    background-color: #eaeaea;
    border-bottom: 1px solid #e0e0e0;
}

.chat-companion-name {
    margin: 0;
}

.chat-messages {
    display: flex;
    flex-direction: column;
    max-height: 100%;
    max-width: 100%;
    min-width: 100%;
    overflow: scroll;
}

.message-avatar {
    width: 40px; /* Размер аватара */
    height: 40px;
    border-radius: 50%; /* Круглый аватар */
    object-fit: cover; /* Обрезает изображение, чтобы оно заполняло элемент */
    margin-right: 10px; /* Расстояние от аватара до текста сообщения */
}

.message {
    max-width: 40%; /* максимальная ширина сообщения */
    margin-bottom: 10px; /* отступ снизу для сообщения */
    padding: 10px; /* отступ внутри блока сообщения */
    border-radius: 18px; /* скругление углов блока сообщения */
    word-wrap: break-word; /* перенос слов внутри блока сообщения */
}

.my-message {
    align-self: flex-end; /* выравнивание блока сообщения по правому краю */
    background-color: #DCF8C6; /* цвет фона для отправленных сообщений */
    margin-right: 10px;
}

.their-message {
    align-self: flex-start; /* выравнивание блока сообщения по левому краю */
    background-color: #ECECEC; /* цвет фона для полученных сообщений */
    margin-left: 10px;
}

.message-content {
    word-wrap: break-word; /* Перенос слов */
}

.chat-input-container {
    display: flex;
    padding: 20px;
    background-color: #fafafa;
    border-top: 1px solid #e0e0e0;
}

.chat-input {
    flex-grow: 1;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 20px;
    margin-right: 10px;
}

.send-button {
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 20px;
    cursor: pointer;
}

.send-button:hover {
    background-color: #0056b3;
}

.message-time {
    color: #999;
    font-size: 0.75rem;
    align-self: flex-end; /* Выравнивание с нижней частью блока сообщения */
}

.message-sender {
    font-size: 0.875rem;
    color: #333;
    margin-bottom: 5px; /* Расстояние до текста сообщения */
}
</style>

