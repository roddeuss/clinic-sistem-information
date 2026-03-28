@once
    @push('scripts')
        <script>
            if (! window.queueBoardHelpers) {
                window.queueBoardHelpers = {
                    queueDesk(config) {
                        return {
                            branchChannel: config.branchChannel,
                            boardEndpoint: config.boardEndpoint,
                            watchedDate: config.watchedDate,
                            liveBoard: config.initialLiveBoard || [],
                            sectionSummaries: config.initialSectionSummaries || [],
                            activeCounter: config.activeCounter || null,
                            liveConnected: false,
                            lastSyncedAt: null,
                            pollTimer: null,
                            init() {
                                this.refreshBoard(false);
                                this.connect();
                                this.startPolling();
                            },
                            connect() {
                                if (! window.Echo || ! this.branchChannel) {
                                    return;
                                }

                                this.liveConnected = true;

                                window.Echo.channel(this.branchChannel)
                                    .listen('.queue.updated', (event) => {
                                        if (event?.queue?.queue_date && event.queue.queue_date !== this.watchedDate) {
                                            return;
                                        }

                                        this.refreshBoard();
                                    });
                            },
                            startPolling() {
                                window.clearInterval(this.pollTimer);
                                this.pollTimer = window.setInterval(() => this.refreshBoard(false), 15000);
                            },
                            refreshBoard(markConnected = true) {
                                if (! this.boardEndpoint) {
                                    return;
                                }

                                const url = new URL(this.boardEndpoint, window.location.origin);
                                url.searchParams.set('date', this.watchedDate);

                                fetch(url, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                })
                                    .then((response) => response.json())
                                    .then((payload) => {
                                        this.liveBoard = payload.liveBoard || [];
                                        this.sectionSummaries = payload.sectionSummaries || [];
                                        this.activeCounter = payload.activeCounter || this.activeCounter;
                                        this.lastSyncedAt = new Date().toLocaleTimeString('id-ID');

                                        if (markConnected) {
                                            this.liveConnected = true;
                                        }
                                    })
                                    .catch(() => {
                                        this.liveConnected = false;
                                    });
                            },
                            formatStatus(status) {
                                return String(status || '-').replaceAll('_', ' ').toUpperCase();
                            },
                            boardStatusClass(status) {
                                return status === 'called'
                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                    : 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300';
                            },
                            safe(value, fallback = '-') {
                                return value || fallback;
                            },
                        };
                    },
                };
            }
        </script>
    @endpush
@endonce
