<template>
    <div v-if="!userId" class="container">
        <p class="info-text">Для использования сервиса необходимо авторизоваться</p>
        <button class="login-button" @click="login">Войти</button>
    </div>
    <div v-if="userId" class="chat-app">
        <aside class="sidebar">
            <div class="search-container">
                <input type="text" v-model="searchQuery" @input="searchUsers" placeholder="Поиск пользователя...">
            </div>
            <div class="search-results">
                <ul v-if="searchResults.length">
                    <li v-for="user in searchResults" :key="user.id" @click="createDialogWithUser(user.id)">
                        <img class="search-avatar" :src="user.avatar" alt="Avatar">
                        {{ user.name }}
                    </li>
                </ul>
            </div>
            <ul class="chat-list">
                <li v-for="chat in chats" :key="chat.id" @click="openChat(chat.id)" class="chat-item">
                    <img v-if="chat.last_message" class="chat-item-avatar" :src="chat.avatar" alt="Avatar">
                    <div v-if="chat.last_message" class="chat-item-details">
                        <h5 class="chat-item-name">{{ chat.companion_name }}</h5>
                        <p class="chat-item-last-message">{{
                                chat.last_message.sender_id.toString() === this.userId.toString() ? 'Вы: ' + chat.last_message.text : chat.last_message.sender_name + ': ' + chat.last_message.text
                            }}</p>
                    </div>
                    <span v-if="chat.last_message" class="chat-item-timestamp">{{ chat.last_message.timestamp }}</span>
                </li>
            </ul>
            <div v-if="isMenuVisible" class="user-menu-btns">
                <div @click="logout" class="user-menu-btn">Выйти</div>
            </div>
            <div class="user-menu">
                <img class="user-menu_avatar" :src="userInfo.avatar" alt="avatar" @click="toggleMenu">
                <div class="user-menu_name">{{ userInfo.name }}</div>
            </div>
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
            <div class="chat-messages" ref="chatMessages">
                <div v-for="message in currentChat.messages" :key="message.id"
                     :class="{'message': true, 'my-message': message.user.id.toString() === this.userId.toString(), 'their-message': message.user.id.toString() !== this.userId.toString()}">
                    <img :src="message.user.avatar" class="message-avatar">
                    <div class="message-content">{{ message.text }}</div>
                    <div class="message-time">{{ message.timestamp }}</div>
                </div>
            </div>
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
import {Centrifuge} from 'centrifuge';

export default {
    data() {
        return {
            centrifuge: null,
            chats: [],
            currentChat: null,
            openedChatSub: null,
            userId: localStorage.getItem('userId'),
            userStatusSub: null,
            newMessage: '',
            otherUserIsTyping: null,
            notifyTyping: false,
            lastTypingTime: null,
            typingInterval: 4000,
            userInfo: null,
            isMenuVisible: false,
            searchQuery: '',
            searchResults: [],
        };
    },

    created() {
        if (localStorage.getItem('access_token')) {
            this.getUserInfo();
            this.fetchChats();
            this.centrifuge = new Centrifuge('ws://5.35.83.190:8000/connection/websocket');
            this.centrifuge.setToken(localStorage.getItem('centrifugo_token'));
            this.centrifuge.connect();

            this.listenForChatUpdates();
            window.addEventListener('click', (event) => {
                if (!event.target.classList.contains('user-menu-btn') && !event.target.classList.contains('user-menu_avatar')) {
                    this.closeMenu();
                    this.searchResults = {};
                }
            });
        }
    },
    methods: {
        searchUsers() {
            if (this.searchQuery.length > 1) {
                axios.get(`/api/search-users?search=${this.searchQuery}`, {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('access_token')}`
                    }
                })
                    .then(response => {
                        this.searchResults = response.data;
                    })
                    .catch(error => {
                        console.error('Ошибка поиска пользователей:', error);
                    });
            }
        },
        createDialogWithUser(userId) {
            axios.post('/api/chats', {
                companionId: userId
            }, {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then((response) => {
                    this.openChat(response.data.chatId)
                })
                .catch((error) => {
                    console.log('Ошибка создания чата '.error.response.data.message)
                })
        },
        login() {
            window.location.href = '/login'
        },
        logout() {
            localStorage.removeItem('access_token')
            localStorage.removeItem('centrifugo_token')
            localStorage.removeItem('userId')
            window.location.href = '/login'
        },
        closeMenu() {
            this.isMenuVisible = false;
        },
        toggleMenu() {
            this.isMenuVisible = !this.isMenuVisible;
        },
        listenForTyping() {
            const sub = this.centrifuge.newSubscription(`chat.${this.currentChat.chat_id}`)
            sub.on('publication', (response) => {
                const data = JSON.parse(response.data)
                if (data.userId != this.userId) {
                    this.otherUserIsTyping = data.typing;
                }
            })
            sub.subscribe()

            this.listenForTypingSub = sub;
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
            }, 5000);
        },
        handleInput() {
            const now = Date.now();
            if (!this.lastTypingTime || now - this.lastTypingTime >= this.typingInterval) {
                this.lastTypingTime = now;
                this.sendTypingNotification();
            }
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
        getUserInfo() {
            axios.get('/api/get-user-info', {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then((response) => {
                    this.userInfo = response.data
                })
                .catch((error) => {
                    console.error('Ошибка при получении информации о пользователе:', error);
                });
        },
        listenForChatMessages(chatId) {
            const sub = this.centrifuge.newSubscription(`user-${chatId}-messages`);
            sub.on('publication', (response) => {
                this.currentChat.messages.push(JSON.parse(response.data))
                this.scrollToBottom();
            });
            sub.subscribe()
            this.openedChatSub = sub
        },
        updateUserStatus(isOnline, lastSeen) {
            this.currentChat.companion.is_online = isOnline
            this.currentChat.companion.last_seen_at = lastSeen
        },
        listenForChangeUserStatus(userId) {
            const sub = this.centrifuge.newSubscription(`user-${userId}-status`)
            sub.on('publication', (response) => {
                const data = JSON.parse(response.data)
                this.updateUserStatus(data.isOnline, data.lastSeen);
            })
            sub.subscribe()
            this.userStatusSub = sub;
        },
        openChat(chatId) {
            axios.get(`/api/chats/${chatId}/messages`, {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('access_token')}`
                }
            })
                .then(response => {
                    if (this.openedChatSub) {
                        this.openedChatSub.unsubscribe()
                        this.centrifuge.removeSubscription(this.openedChatSub)

                        this.userStatusSub.unsubscribe()
                        this.centrifuge.removeSubscription(this.userStatusSub)

                        this.listenForTypingSub.unsubscribe()
                        this.centrifuge.removeSubscription(this.listenForTypingSub)
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
                    const chats = [];
                    response.data.forEach(chat => {
                        if (chat.last_message) {
                            chats.push(chat)
                        }
                    })
                    this.chats = chats;
                })
                .catch(error => {
                    console.error('Ошибка при загрузке чатов:', error);
                });
        },
        listenForChatUpdates() {
            if (!this.userId) {
                console.log('undefined user id')
            }
            const sub = this.centrifuge.newSubscription(`user-${this.userId}-chats`);
            sub.on('publication', (response) => {
                this.chats = JSON.parse(response.data)
            });
            sub.subscribe()
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
    position: relative;
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
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    object-fit: cover;
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
    position: relative;
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
    max-height: calc(100vh - 170px);
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
    position: absolute;
    bottom: 0;
    width: 100%;
    display: flex;
    padding: 20px;
    background-color: #fafafa;
    border-top: 1px solid #e0e0e0;
}

.chat-input {
    flex-grow: 1;
    padding: 10px;
    max-width: 84%;
    border: 1px solid #ccc;
    border-radius: 20px;
    margin-right: 10px;
}

.send-button {
    padding: 10px 20px;
    background-color: #007bff;
    width: 10%;
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

.user-menu {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45px;
    background-color: #eaeaea;
    border-top: 1px solid #e0e0e0;
    color: #fff;
}

.user-menu_avatar {
    cursor: pointer;
    width: 45px; /* Уменьшить ширину изображения до 40px */
    height: 45px; /* Уменьшить высоту изображения до 40px */
    border-radius: 50%; /* Сделать изображение круглым */
    position: absolute; /* Абсолютное позиционирование позволяет разместить изображение в углу */
    top: 0; /* Расположение в верхней части блока */
    left: 0; /* Расположение в левой части блока */
}

.user-menu_name {
    margin-left: 50px;
    color: black;
    font-weight: bold;
    line-height: 45px;
}

.user-menu-btns {
    position: absolute;
    bottom: 45px;
    left: 0;
    display: flex;
    flex-direction: column;
    padding-left: 10px;
    background-color: #eaeaea;
    border-top: 1px solid #e0e0e0;
    width: 100%;
}

.user-menu-btn {
    padding-left: 50px;
    color: black;
    line-height: 45px;
    cursor: pointer;
    width: 100%;
}

.container {
    text-align: center;
}

.info-text {
    font-size: 20px;
    color: #333;
    margin-bottom: 20px;
}

.login-button {
    font-size: 18px;
    padding: 10px 20px;
    color: white;
    background-color: #007bff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.login-button:hover {
    background-color: #0056b3;
}

.search-container {
    padding: 10px;
    background-color: #eaeaea;
    border-bottom: 1px solid #e0e0e0;
}

.search-container input {
    width: calc(100% - 15px);
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-results {
    position: absolute;
    width: 100%;
    background-color: #fff;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 100;
}

.search-results ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.search-results li {
    display: flex;
    align-items: center; /* Выравнивание содержимого по центру по вертикали */
    padding: 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.search-results li:hover {
    background-color: #f7f7f7;
}

.search-avatar {
    width: 50px; /* Размер аватара */
    height: 50px;
    border-radius: 50%;
    margin-right: 15px;
    object-fit: cover; /* Обрезает изображение, чтобы оно заполняло элемент */
}
</style>

