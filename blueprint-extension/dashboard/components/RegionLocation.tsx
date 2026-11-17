import React, { useEffect, useState } from 'react';
import { faGlobe, faServer } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import StatBlock from '@/components/server/console/StatBlock';
import { ServerContext } from '@/state/server';

interface LocationData {
    status: 'success' | 'local' | 'invalid' | 'rate_limited' | 'unavailable';
    city: string | null;
    country_name: string;
    country_code: string;
}

export default () => {
    const [location, setLocation] = useState<LocationData | null>(null);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const hasAllocation = ServerContext.useStoreState((state) =>
        state.server.data!.allocations.some((allocation) => allocation.isDefault)
    );

    useEffect(() => {
        let active = true;
        setLocation(null);

        if (!hasAllocation) {
            return;
        }

        http.get<LocationData>(`/api/client/extensions/pterodactyllocation/servers/${uuid}/region`, { timeout: 15000 })
            .then(({ data }) => {
                if (active) setLocation(data);
            })
            .catch(() => {
                if (active) {
                    setLocation({
                        status: 'unavailable',
                        city: null,
                        country_name: 'Location unavailable',
                        country_code: '',
                    });
                }
            });

        return () => {
            active = false;
        };
    }, [uuid, hasAllocation]);

    return (
        <StatBlock icon={location?.status === 'local' ? faServer : faGlobe} title={'Region'}>
            {!hasAllocation ? 'No allocation' : location ? location.country_name : 'Loading...'}
            {location?.city && <span className={'ml-1 text-gray-300 text-[70%] select-none'}>, {location.city}</span>}
        </StatBlock>
    );
};