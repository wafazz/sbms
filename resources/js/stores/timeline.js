import { defineStore } from 'pinia';
import { http } from '../api/http.js';

/**
 * Tasks, milestones and the team list for one project.
 * Both the Timeline tab and the Tasks tab use this store.
 */
export const useTimelineStore = defineStore('timeline', {
    state: () => ({
        project: null,
        today: null,
        tasks: [],
        milestones: [],
        team: [],
        loading: false,
    }),
    actions: {
        async load(projectId) {
            this.loading = true;
            try {
                const { data } = await http.get(`/projects/${projectId}/timeline`);
                this.project = data.data.project;
                this.today = data.data.today;
                this.tasks = data.data.tasks;
                this.milestones = data.data.milestones;
            } finally {
                this.loading = false;
            }
        },
        async loadTeam() {
            if (this.team.length) return;
            const { data } = await http.get('/team');
            this.team = data.data;
        },
        async createTask(projectId, payload) {
            const { data } = await http.post(`/projects/${projectId}/tasks`, payload);
            return data.data;
        },
        async updateTask(projectId, taskId, payload) {
            const { data } = await http.put(`/projects/${projectId}/tasks/${taskId}`, payload);
            return data.data;
        },
        /** Drag or resize on the Gantt chart. Dates only. */
        async scheduleTask(projectId, taskId, start_date, end_date) {
            const { data } = await http.patch(`/projects/${projectId}/tasks/${taskId}/schedule`, { start_date, end_date });
            return data.data;
        },
        async deleteTask(projectId, taskId) {
            await http.delete(`/projects/${projectId}/tasks/${taskId}`);
        },
        async createMilestone(projectId, payload) {
            const { data } = await http.post(`/projects/${projectId}/milestones`, payload);
            return data.data;
        },
        async updateMilestone(projectId, id, payload) {
            const { data } = await http.put(`/projects/${projectId}/milestones/${id}`, payload);
            return data.data;
        },
        async deleteMilestone(projectId, id) {
            await http.delete(`/projects/${projectId}/milestones/${id}`);
        },
    },
});
