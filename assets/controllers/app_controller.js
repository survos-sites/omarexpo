import MobileController from '@survos-mobile/mobile';
import Twig from 'twig';
// app_controller must extend from MobileController, and be called app, otherwise outlets won't work.

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://github.com/symfony/stimulus-bridge#lazy-controllers
*/
/* stimulusFetch: 'lazy' */
export default class extends MobileController {
    // static targets = ['tab'];
    connect() {
        super.connect();
        console.log('hello from %s | %d tabs', this.identifier, this.tabTargets.length);
        this.tabTargets.forEach(t => {
            const p = t.getAttribute('page');
            console.assert(p, t);
        });

        // from dexie_controller.  Should be able to call via the outlet, too.
        document.addEventListener('window.db.available', async (e) => {
            // console.warn("db is available.  based on url, open a page or tab", window.location);
            // Get the URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            // Extract the 'projectId' parameter value
            const itemId = urlParams.get('itemId');
            const db = window.db;
            if (itemId) {
                console.error(itemId);
                // const allTours = await db.tours.toArray();
                const matchingTour = await db.items.where({ code: itemId }).first();
                this.navigatorTarget.pushPage('player', {data: {id: matchingTour.id}});

                // this is an array of results, we just and the first one.
                // if we were opening a tab.  Should be generalized
                // this.tabbarTarget.setActiveTab(1, {animation: 'none'}); // hack!
            }

        });

        document.addEventListener('share.prechange', (e) => {
            return;
            // this code is from when share needed to know the active project
            const data = {id: this.getCurrentProjectId()};
            // console.error(data);
            // const activeTabIndex = e.detail.activeIndex;
            const activeTabIndex = e.detail.index;

            console.error(e, e.detail);
            const tabItem = e.detail.tabItem;
            // tabItem.setAttribute('data', "icon: 'base2.gif', url: 'output.htm', target: 'AccessPage', output: '1'");
            tabItem.dataset.projectId=this.getCurrentProjectId();
            console.warn(tabItem, activeTabIndex);
            document.dispatchEvent(new CustomEvent('dexie.share', {'detail':
                data
            }));

            // let tab = this.tabTargets.find(x => x.getAttribute('page') === 'tours');
            this.tabbarTarget.setActiveTab(2, {
                callback: (e) => console.error(e)
            });
        });
        document.addEventListener('tours.prechange', (e) => {
            const activeTabIndex = e.detail.index;
            // this.navigatorTarget.topPage
            // this.tabTargets[activeTabIndex].load('tours');
            console.warn(e.type);
            this.tabbarTarget.setActiveTab(activeTabIndex, {data: {a: 'b'}});
            return;
            let tab = this.tabTargets.find(x => x.getAttribute('page') === 'tours');
            tab.loadPage('tours', {data: {a: 'b'}});
            this.tabbarTarget.loadPage('tours', {data: {a: 'b'}});
        });

            // we can do this in app, mobilecontroller has no need for dexie
        // db.open().then(db =>
        //     db.savedTable.count().then( c => console.log(c)));

    }

    disconnect() {
        console.error('disconnecting from ' + this.identifier);
    }

    initialize() {
        // console.log('init ' + this.identifier);
        // if (!this.hasOwnProperty('tabs')) {
        //     this.tabs = {};
        // }
    }

    clear() {
        this.menuTarget.close();
        window.db.delete().then(() => window.db.open());
    }

    close() {
        this.menuTarget.close();
        window.close();
    }

    locale(e) {
        console.log(e, e.type, e.target.value);
        window.location.href = e.target.value;
        console.log(window.location);
    }

    async player(e) {
        const id = e.params.id;
        console.error(e.params);
        this.navigatorTarget.pushPage('player', {data: e.params});
        return;
        const data = db.table(e.params.store).get(id).then(
            (data) => {
                console.log(data);
                console.assert(data, "Missing data for " + id);
                this.navigatorTarget.pushPage('detail', {data: data}).then(
                    (p) => {
                        // p.data is the same as data
                        if (this.hasTwigTemplateTarget) {
                            let template = Twig.twig({
                                data: this.twigTemplateTarget.innerHTML
                            });
                            let html = template.render(data);
                            if (this.hasDetailTarget) {
                                this.detailTarget.innerHTML = html;
                            } else {
                                console.error('no detail target');
                            }
                        }
                    }
                );

            }
        );

    }

    add(e) {
        console.error(e);
        // get the row and toggle the 'owned' property
        db.savedTable.get(e.params.id)
            .then((row) => {
                row.owned = e.target.closest("ons-switch").checked;
                db.savedTable.put(row).then(() => this.updateSavedCount());
            })
            .catch(e => console.error(e));
    }

    log(x) {
        console.log(x);
    }

    async switch(e) {
        return;
        // Example usage
        const db = window.db;
        console.log(db.projects);
        let foundProject = null;
        await db.projects.each(project => {
            if (project.id === e.params.id) {
            foundProject = project;
            console.log('Project found:', project);
            return false; // Stop iterating once the project is found
            }
        });

        // const project = await db.projects.where('id').equals(e.params.id).first();
        // console.log('RRRRRRRRRRRRRBBBBBBBBBBBBBNNNNN-------', project,e.params.id);
        let newParams = { projectId: foundProject.code };
        // Modify the current URL and assign it to window.location
        window.location.href = this.modifyUrl(window.location.href, newParams);
        console.log(e.params,this.modifyUrl(window.location.href, newParams));
        const key = 'activeProject';
        localStorage.setItem(key, e.params.id);
        this.setCurrentProjectId(e.params.id); // @todo: bind to icon on tabbar
        let tab = this.tabTargets.find(x => x.getAttribute('page') === 'tours');
        this.tabbarTarget.setActiveTab(1);
        // search children!  closest() returns ancestors.
        console.assert(tab, "missing tab tours ");
        let badge = tab.querySelector('.tabbar__badge');
        badge.innerText = this.getCurrentProjectId();
    }

    modifyUrl(url, newParams) {
        // Parse the URL
        let parsedUrl = new URL(url);
        // Clear existing query parameters
        parsedUrl.search = '';
        // Add new query parameters
        for (let key in newParams) {
          parsedUrl.searchParams.append(key, newParams[key]);
        }
        return parsedUrl.toString();
      }

    async open_page(e) {
        const id = e.params.id;
        console.log(e.params);
        const data =window.db.table(e.params.store).get(id).then(
            (data) => {
                console.log(data, e.params);
                console.assert(data, "Missing data for " + id);
                this.navigatorTarget.pushPage(e.params.page, {data: {id: id}}).then(
                    (p) => {
                        // console.error(p);
                        // // events?
                        // return;
                        // // p.data is the same as data
                        // if (this.hasTwigTemplateTarget) {
                        //     let template = Twig.twig({
                        //         data: this.twigTemplateTarget.innerHTML
                        //     });
                        // let html = template.render(data);
                        //     if (this.hasDetailTarget) {
                        //         this.detailTarget.innerHTML = html;
                        //     } else {
                        //         console.error('no detail target');
                        //     }
                        // }
                    }
                );

            }
        );

    }

    getFilter(refreshEvent) {
        return {};
    }

    async isPopulated(t){
        console.log('--------------',t);
        // if we're on the 'tours' tab, load up the tours with this project id
        if(t.name==='tours'){
            const db = window.db;
            console.log(db.projects);
            const urlParams = new URLSearchParams(window.location.search);
            console.error(urlParams.toString());
            const projectId = urlParams.get('projectId');
            if(!projectId){
                return false;
            }
            const matchingTourCount = await db.tours.where({ projectCode: projectId }).count();
            const project = await db.projects.where({code: projectId}).first();

            if(matchingTourCount === project.itemCount) {
                return true;
            }
            return false;
            // console.log(matchingTourCount,project, 'bbbbbbbb');
            // return Boolean(matchingTourCount);
        }
        const count = await new Promise((resolve, reject) => {
            t.count(count => resolve(count)).catch(reject);
        });
        return count > 0;
    }

    getProjectFiltered(stores){
        console.log("ZZZZZZZ", stores)
        const urlParams = new URLSearchParams(window.location.search);
        // Extract the 'projectId' parameter value
        console.error(urlParams);
        const itemId = urlParams.get('itemId');
        const projectId = urlParams.get('projectId');
        stores.forEach((store)=> {
            if(store.name === 'tours'){
                store.url = store.url + `?project=/api/projects/${projectId}`
            }
        })
        return stores;
    }

    tabTargetConnected(x) {
        const pageName = x.getAttribute('page');
        console.assert(pageName, x);
        if (!this.hasOwnProperty('tabs')) {
            this.tabs = {};
            console.warn('clearing tabs');
        } else {
        }
        this.tabs[pageName] = x;
    }

    setDb(db) {
        super.setDb(db, false);
        db = window.db;

        // https://github.com/dexie/Dexie.js/issues/1967
        // https://dexie.org/docs/Collection/Collection.first()
        db.tables.forEach(t=>{
            t.count(c => console.error('count of ' + t.name + ' is  ' + c));
            // t.first(item => console.error(item));
            // t.first()
            //     .then(item => console.error(item))
            //     .catch(err => console.error(err))
            //     .finally(() => console.log('finally!'))
        });
        db.items.count().then(c => console.error(c));
        // db.projects.each(e => console.error(e))
    }

    messageTargetConnected(element) {
        // this.messageTarget.innerHTML = ''
    }

    savedCountTargetConnected(element) {
        this.updateSavedCount();
    }

    clearLocalStorage() {
        localStorage.clear();
        this.db.delete().then(() => db.open());
        ons.notification.alert('Cleared local storage');
    };

    updateSavedCount() {
        db.savedTable.filter(n => n.owned).count().then(count => {
            this.savedCountTarget.innerHTML = count;
        });

    }


}
