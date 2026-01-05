// Vuno Offline Functionality
class VunoOffline {
    constructor() {
        this.offlineData = {
            crops: this.getDefaultCrops(),
            diseases: this.getDefaultDiseases(),
            marketPrices: this.getCachedPrices(),
            plantingAdvice: this.getGeneralAdvice()
        };
        
        this.initOfflineStorage();
    }
    
    initOfflineStorage() {
        // Initialize IndexedDB for offline storage
        if (!window.indexedDB) {
            console.warn('IndexedDB not supported, offline features limited');
            return;
        }
        
        const request = indexedDB.open('VunoOfflineDB', 1);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            // Create object stores
            if (!db.objectStoreNames.contains('requests')) {
                db.createObjectStore('requests', { keyPath: 'id', autoIncrement: true });
            }
            
            if (!db.objectStoreNames.contains('chat')) {
                db.createObjectStore('chat', { keyPath: 'id', autoIncrement: true });
            }
            
            if (!db.objectStoreNames.contains('diagnoses')) {
                db.createObjectStore('diagnoses', { keyPath: 'id', autoIncrement: true });
            }
        };
        
        request.onsuccess = (event) => {
            this.db = event.target.result;
            console.log('Offline database initialized');
        };
        
        request.onerror = (event) => {
            console.error('Failed to open IndexedDB:', event.target.error);
        };
    }
    
    getDefaultCrops() {
        return [
            {
                id: 1,
                name: 'Maize',
                local_name: 'Mahindi',
                type: 'cereal',
                planting_season: 'Long rains (March-May)',
                spacing: '75cm rows, 30cm plants',
                water_needs: '5000L/acre/week',
                fertilizer: '100kg CAN, 50kg DAP per acre'
            },
            {
                id: 2,
                name: 'Tomatoes',
                local_name: 'Nyanya',
                type: 'vegetable',
                planting_season: 'Year-round with irrigation',
                spacing: '60cm rows, 45cm plants',
                water_needs: '7000L/acre/week',
                fertilizer: '150kg NPK per acre'
            },
            {
                id: 3,
                name: 'Beans',
                local_name: 'Maharagwe',
                type: 'legume',
                planting_season: 'Short rains (Oct-Dec)',
                spacing: '50cm rows, 10cm plants',
                water_needs: '4000L/acre/week',
                fertilizer: '50kg DAP per acre'
            }
        ];
    }
    
    getDefaultDiseases() {
        return [
            {
                id: 1,
                name: 'Maize Lethal Necrosis',
                symptoms: 'Yellow streaks on leaves, stunted growth',
                treatment: 'Remove infected plants, use certified seeds',
                organic: 'Crop rotation, resistant varieties'
            },
            {
                id: 2,
                name: 'Tomato Early Blight',
                symptoms: 'Brown spots with rings on lower leaves',
                treatment: 'Copper-based fungicide',
                organic: 'Baking soda spray, proper spacing'
            },
            {
                id: 3,
                name: 'Bean Anthracnose',
                symptoms: 'Dark lesions on pods and stems',
                treatment: 'Fungicide spray',
                organic: 'Neem oil, crop rotation'
            }
        ];
    }
    
    getCachedPrices() {
        const cached = localStorage.getItem('vuno_market_cache');
        if (cached) {
            return JSON.parse(cached);
        }
        
        // Default prices if no cache
        return [
            { crop: 'Tomatoes', market: 'Nakuru', price: 180, unit: 'kg' },
            { crop: 'Maize', market: 'Murang\'a', price: 65, unit: 'kg' },
            { crop: 'Kale', market: 'Nairobi', price: 40, unit: 'bunch' },
            { crop: 'Potatoes', market: 'Eldoret', price: 120, unit: 'kg' }
        ];
    }
    
    getGeneralAdvice() {
        return {
            planting: [
                'Prepare land 1-2 weeks before planting',
                'Test soil pH (ideal 6.0-6.8)',
                'Apply well-rotted manure or compost',
                'Plant at onset of rains for rainfed crops',
                'Water early morning or late evening'
            ],
            fertilizer: [
                'Maize: 50kg DAP at planting, 50kg CAN top-dress',
                'Tomatoes: 150kg NPK per acre',
                'Beans: 50kg DAP per acre',
                'Always follow soil test recommendations'
            ],
            pest_control: [
                'Use neem oil spray for general pests',
                'Practice crop rotation',
                'Remove and burn infected plants',
                'Use physical barriers where possible'
            ]
        };
    }
    
    // Store API request for later sync
    storeOfflineRequest(endpoint, method, data) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['requests'], 'readwrite');
        const store = transaction.objectStore('requests');
        
        const request = {
            endpoint,
            method,
            data,
            timestamp: new Date().toISOString(),
            attempts: 0
        };
        
        return new Promise((resolve, reject) => {
            const addRequest = store.add(request);
            
            addRequest.onsuccess = () => resolve();
            addRequest.onerror = () => reject(addRequest.error);
        });
    }
    
    // Get stored offline requests
    getOfflineRequests() {
        if (!this.db) return Promise.resolve([]);
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['requests'], 'readonly');
            const store = transaction.objectStore('requests');
            const requests = [];
            
            store.openCursor().onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    requests.push(cursor.value);
                    cursor.continue();
                } else {
                    resolve(requests);
                }
            };
            
            store.openCursor().onerror = (event) => {
                reject(event.target.error);
            };
        });
    }
    
    // Clear processed requests
    clearOfflineRequest(id) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['requests'], 'readwrite');
        const store = transaction.objectStore('requests');
        store.delete(id);
    }
    
    // Store chat messages offline
    storeChatMessage(sessionId, message, sender) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['chat'], 'readwrite');
        const store = transaction.objectStore('chat');
        
        const chatData = {
            sessionId,
            message,
            sender,
            timestamp: new Date().toISOString(),
            synced: false
        };
        
        store.add(chatData);
    }
    
    // Get offline chat history
    getChatHistory(sessionId) {
        if (!this.db) return Promise.resolve([]);
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['chat'], 'readonly');
            const store = transaction.objectStore('chat');
            const messages = [];
            
            store.openCursor().onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    if (cursor.value.sessionId === sessionId) {
                        messages.push(cursor.value);
                    }
                    cursor.continue();
                } else {
                    resolve(messages);
                }
            };
        });
    }
    
    // Resource calculator (works offline)
    calculateResourcesOffline(crop, plotSize, resourceType) {
        const calculations = {
            maize: {
                seeds: plotSize * 20,
                water: plotSize * 5000,
                fertilizer: plotSize * 100,
                labor: Math.ceil(plotSize * 8),
                cost: plotSize * 15000
            },
            tomatoes: {
                seeds: plotSize * 0.1,
                water: plotSize * 7000,
                fertilizer: plotSize * 150,
                labor: Math.ceil(plotSize * 12),
                cost: plotSize * 25000
            },
            beans: {
                seeds: plotSize * 30,
                water: plotSize * 4000,
                fertilizer: plotSize * 50,
                labor: Math.ceil(plotSize * 6),
                cost: plotSize * 8000
            }
        };
        
        const cropKey = crop.toLowerCase();
        const calc = calculations[cropKey] || calculations.maize;
        
        if (resourceType === 'all') {
            return calc;
        }
        
        return { [resourceType]: calc[resourceType] };
    }
    
    // Disease diagnosis offline
    diagnoseOffline(symptoms, crop) {
        const keywords = {
            'yellow': 'Nutrient deficiency or viral infection',
            'spots': 'Fungal or bacterial infection',
            'wilt': 'Water stress or root disease',
            'holes': 'Insect pest damage',
            'mold': 'Fungal growth, improve ventilation'
        };
        
        let diagnosis = 'General plant health issue';
        let confidence = 0;
        
        for (const [keyword, advice] of Object.entries(keywords)) {
            if (symptoms.toLowerCase().includes(keyword)) {
                diagnosis = advice;
                confidence = 60;
                break;
            }
        }
        
        return {
            disease_name: diagnosis,
            confidence: confidence,
            urgency: confidence > 50 ? 'medium' : 'low',
            recommendations: [
                'Remove severely affected parts',
                'Apply organic treatment (neem oil, baking soda)',
                'Improve growing conditions',
                'Consult expert when online for precise diagnosis'
            ]
        };
    }
}

// Initialize offline functionality
window.vunoOffline = new VunoOffline();